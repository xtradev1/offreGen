<?php

declare(strict_types=1);

namespace OffreGen\Http\Controleur;

use OffreGen\Catalogue\Recherche;
use OffreGen\Catalogue\SaisieProduit;
use OffreGen\Donnees\DepotFactures;
use OffreGen\Donnees\Portee;
use OffreGen\Donnees\SupportLignes;
use OffreGen\Http\Conflit;
use OffreGen\Http\Conteneur;
use OffreGen\Http\Flash;
use OffreGen\Http\Formats;
use OffreGen\Http\PageIntrouvable;
use OffreGen\Http\Parametres;
use OffreGen\Offre\Saisie;
use OffreGen\Offre\SaisieRefusee;
use OffreGen\Offre\Tva;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Les corrections de l'artisan sur les lignes d'une offre, ou d'une facture en brouillon : quantité, unité, prix,
 * désignation, « C'est juste », suppression annulable, ajout depuis le catalogue ou en ligne libre.
 * Aucune ne coûte d'appel au modèle (CODING_RULES, Ergonomie). Une facture émise ou annulée répond 409.
 *
 * @phpstan-type Cible array{id: int, base: string, retour: string, document: string, ce: string}
 * @phpstan-type LigneVue array{id: int, designation: string, quantite_milliemes: int, unite: string, prix_centimes: int, produit_id: int|null, categorie: string|null, tva_pour_dix_mille: int|null, tva_ligne: int|null}
 */
final class LigneControleur
{
    public const OFFRE = 'offre';
    public const FACTURE = 'facture';

    /** @param self::OFFRE|self::FACTURE $genre */
    public function __construct(private readonly Conteneur $c, private readonly string $genre = self::OFFRE)
    {
    }

    /** @param array<string, string> $parametres */
    public function page(Request $requete, array $parametres): Response
    {
        [$cible, $l] = $this->ligne($requete, $parametres);
        $champs = ['designation' => $l['designation'], 'quantite' => Formats::saisieQuantite($l['quantite_milliemes']), 'unite' => $l['unite'],
                   'prix' => Formats::saisiePrix($l['prix_centimes']), 'categorie' => '', 'nouvelle_categorie' => '',
                   'tva' => $l['tva_ligne'] === null ? '' : (string) $l['tva_ligne']];
        return $this->formulaire($requete, $cible, $l, $champs, [], 200);
    }

    /**
     * Une ligne de l'écran envoie seulement le champ touché (quantite ou prix) ; la page de la ligne
     * les envoie tous. Les champs absents gardent leur valeur.
     *
     * @param array<string, string> $parametres
     */
    public function corriger(Request $requete, array $parametres): Response
    {
        [$cible, $l] = $this->ligne($requete, $parametres);
        $r = $requete->request;
        $depuisPage = $r->has('designation');
        $erreurs = [];
        $designation = $r->has('designation') ? Saisie::texte($r->getString('designation'), 255) : $l['designation'];
        if ($designation === null) {
            $erreurs['designation'] = 'Indiquez la désignation (255 caractères au plus).';
        }
        $quantite = $r->has('quantite') ? Saisie::milliemes($r->getString('quantite')) : $l['quantite_milliemes'];
        if ($quantite === null) {
            $erreurs['quantite'] = 'Quantité plus grande que 0, par exemple 12 ou 12.5.';
        }
        $unite = $r->has('unite') ? Saisie::unite($r->getString('unite'), $l['unite']) : $l['unite'];
        if ($unite === null) {
            $erreurs['unite'] = 'Choisissez une unité.';
        }
        $prix = $r->has('prix') ? Saisie::centimes($r->getString('prix')) : $l['prix_centimes'];
        if ($prix === null) {
            $erreurs['prix'] = 'Prix en francs, par exemple 28 ou 28.50.';
        }
        // Le taux, depuis la page seulement (D11) ; absent, il ne change pas.
        $tva = $r->has('tva') ? Tva::lire($r->getString('tva')) : $l['tva_ligne'];
        if ($tva === false) {
            $erreurs['tva'] = 'Choisissez un taux de TVA.';
        }
        $produit = null;
        if ($depuisPage && $l['produit_id'] === null && $r->getString('garder') === '1' && $erreurs === []) {
            try {
                $produit = SaisieProduit::lire(['designation' => (string) $designation, 'unite' => (string) $unite, 'prix' => $r->getString('prix'),
                    'categorie' => $r->getString('categorie'), 'nouvelle_categorie' => $r->getString('nouvelle_categorie'), 'synonymes' => '',
                    'tva' => $r->getString('tva')]);
            } catch (SaisieRefusee $e) {
                $erreurs += $e->erreurs;
            }
        }
        if ($designation === null || $quantite === null || $unite === null || $prix === null || $tva === false || $erreurs !== []) {
            if ($depuisPage) {
                return $this->formulaire($requete, $cible, $l, self::champs($requete), $erreurs, 422);
            }
            Flash::dire($requete, 'erreur', implode(' ', $erreurs) . ' Rien n\'a été modifié.');
            return $this->versLaLigne($cible, $l['id']);
        }

        $support = $this->support($requete);
        $support->corrigerLigne($cible['id'], $l['id'], $designation, $quantite, $unite, $prix);
        if ($tva !== $l['tva_ligne']) {
            $support->changerTauxLigne($cible['id'], $l['id'], $tva);
        }
        if ($produit !== null) {
            $support->rattacherProduit($cible['id'], $l['id'], $this->c->produits($this->portee($requete))->creer($produit));
            Flash::dire($requete, 'info', Flash::cite($produit['designation']) . ' ajouté au catalogue');
        }
        $this->modifiee($requete, $cible['id']);
        return $this->versLaLigne($cible, $l['id']);
    }

    /** @param array<string, string> $parametres */
    public function confirmer(Request $requete, array $parametres): Response
    {
        [$cible, $l] = $this->ligne($requete, $parametres);
        $this->support($requete)->confirmerLigne($cible['id'], $l['id']);
        $this->modifiee($requete, $cible['id']);
        return $this->versLaLigne($cible, $l['id']);
    }

    /** @param array<string, string> $parametres */
    public function supprimer(Request $requete, array $parametres): Response
    {
        [$cible, $l] = $this->ligne($requete, $parametres);
        $this->support($requete)->supprimerLigne($cible['id'], $l['id'], $this->c->horloge()->now());
        $this->modifiee($requete, $cible['id']);
        Flash::dire($requete, 'annuler', ['action' => $cible['base'] . '/lignes/' . $l['id'] . '/restaurer', 'designation' => $l['designation']]);
        return new RedirectResponse($cible['base'], 303);
    }

    /** @param array<string, string> $parametres */
    public function restaurer(Request $requete, array $parametres): Response
    {
        $cible = $this->cible($requete, $parametres);
        $ligneId = Parametres::id($parametres, 'ligne');
        if (!$this->support($requete)->restaurerLigne($cible['id'], $ligneId)) {
            throw new PageIntrouvable();
        }
        $this->modifiee($requete, $cible['id']);
        Flash::dire($requete, 'info', 'Ligne remise');
        return $this->versLaLigne($cible, $ligneId);
    }

    /** @param array<string, string> $parametres */
    public function pageAjout(Request $requete, array $parametres): Response
    {
        $cible = $this->cible($requete, $parametres);
        $q = mb_substr($requete->query->getString('q'), 0, 100);
        $champs = ['designation' => $q, 'quantite' => '1', 'unite' => 'forfait', 'prix' => '', 'categorie' => '', 'nouvelle_categorie' => '', 'tva' => ''];
        return $this->pageAjoutAvec($requete, $cible, $q, $champs, [], 200);
    }

    /**
     * « produit » : ajout depuis le catalogue, quantité 1, que l'artisan corrige aussitôt.
     * Sinon : ligne libre, gardée au catalogue si « garder » est coché.
     *
     * @param array<string, string> $parametres
     */
    public function ajouter(Request $requete, array $parametres): Response
    {
        $cible = $this->cible($requete, $parametres);
        $r = $requete->request;
        $support = $this->support($requete);
        $produits = $this->c->produits($this->portee($requete));

        if ($r->has('produit')) {
            $p = $produits->parId(Parametres::id(['p' => $r->getString('produit')], 'p')) ?? throw new PageIntrouvable();
            $id = $support->ajouterLigne($cible['id'], $p['designation'], 1000, $p['unite'], $p['prix_centimes'], $p['id']);
            $this->modifiee($requete, $cible['id']);
            Flash::dire($requete, 'focus', $id);
            return $this->versLaLigne($cible, $id);
        }

        $champs = self::champs($requete);
        $erreurs = [];
        $designation = Saisie::texte($champs['designation'], 255);
        if ($designation === null) {
            $erreurs['designation'] = 'Indiquez la désignation (255 caractères au plus).';
        }
        $quantite = Saisie::milliemes($champs['quantite']);
        if ($quantite === null) {
            $erreurs['quantite'] = 'Quantité plus grande que 0, par exemple 1 ou 12.5.';
        }
        $unite = Saisie::unite($champs['unite']);
        if ($unite === null) {
            $erreurs['unite'] = 'Choisissez une unité.';
        }
        $prix = Saisie::centimes($champs['prix']);
        if ($prix === null) {
            $erreurs['prix'] = "Prix en francs, par exemple 280 ou 280.50\u{00A0}; vide si vous ne le connaissez pas encore.";
        }
        $produit = null;
        if ($r->getString('garder') === '1' && $erreurs === []) {
            try {
                $produit = SaisieProduit::lire($champs + ['synonymes' => '']);
            } catch (SaisieRefusee $e) {
                $erreurs += $e->erreurs;
            }
        }
        if ($designation === null || $quantite === null || $unite === null || $prix === null || $erreurs !== []) {
            return $this->pageAjoutAvec($requete, $cible, '', $champs, $erreurs, 422);
        }
        $produitId = $produit === null ? null : $produits->creer($produit);
        $id = $support->ajouterLigne($cible['id'], $designation, $quantite, $unite, $prix, $produitId);
        $this->modifiee($requete, $cible['id']);
        if ($produit !== null) {
            Flash::dire($requete, 'info', Flash::cite($produit['designation']) . ' ajouté à ' . $cible['document'] . ' et au catalogue');
        }
        return $this->versLaLigne($cible, $id);
    }

    private function portee(Request $requete): Portee
    {
        return new Portee($requete->attributes->getInt('_artisan'));
    }

    private function support(Request $requete): SupportLignes
    {
        return $this->genre === self::FACTURE ? $this->c->factures($this->portee($requete)) : $this->c->offres($this->portee($requete));
    }

    /**
     * L'offre, ou la facture en brouillon, qui porte les lignes. 404 si elle n'est pas à l'artisan ; 409 si la
     * facture est émise ou annulée.
     *
     * @param array<string, string> $parametres
     * @return Cible
     */
    private function cible(Request $requete, array $parametres): array
    {
        $id = Parametres::id($parametres, 'id');
        if ($this->genre === self::FACTURE) {
            $f = $this->c->factures($this->portee($requete))->parId($id) ?? throw new PageIntrouvable();
            if ($f['statut'] !== DepotFactures::BROUILLON) {
                throw new Conflit('Cette facture est émise : elle ne se modifie plus.');
            }
            return ['id' => $f['id'], 'base' => '/factures/' . $f['id'], 'retour' => 'Facture', 'document' => 'la facture', 'ce' => 'cette facture'];
        }
        $o = $this->c->offres($this->portee($requete))->parId($id) ?? throw new PageIntrouvable();
        return ['id' => $o['id'], 'base' => '/offres/' . $o['id'], 'retour' => 'Offre', 'document' => "l'offre", 'ce' => 'cette offre'];
    }

    /**
     * @param array<string, string> $parametres
     * @return array{Cible, LigneVue}
     */
    private function ligne(Request $requete, array $parametres): array
    {
        $cible = $this->cible($requete, $parametres);
        $l = $this->support($requete)->ligne($cible['id'], Parametres::id($parametres, 'ligne')) ?? throw new PageIntrouvable();
        return [$cible, $l];
    }

    private function modifiee(Request $requete, int $id): void
    {
        $this->support($requete)->marquerModifiee($id, $this->c->horloge()->now());
    }

    /** @param Cible $cible */
    private function versLaLigne(array $cible, int $ligneId): RedirectResponse
    {
        return new RedirectResponse($cible['base'] . '#l' . $ligneId, 303);
    }

    /** @return array{designation: string, quantite: string, unite: string, prix: string, categorie: string, nouvelle_categorie: string, tva: string} */
    private static function champs(Request $requete): array
    {
        $r = $requete->request;
        return ['designation' => $r->getString('designation'), 'quantite' => $r->getString('quantite'), 'unite' => $r->getString('unite'),
                'prix' => $r->getString('prix'), 'categorie' => $r->getString('categorie'), 'nouvelle_categorie' => $r->getString('nouvelle_categorie'),
                'tva' => $r->getString('tva')];
    }

    /**
     * @param Cible $cible
     * @param LigneVue $l
     * @param array<string, string> $champs
     * @param array<string, string> $erreurs
     */
    private function formulaire(Request $requete, array $cible, array $l, array $champs, array $erreurs, int $code): Response
    {
        $unites = Saisie::UNITES;
        if (!in_array($l['unite'], $unites, true)) {
            $unites[] = $l['unite'];
        }
        return new Response($this->c->twig()->render('ligne.html.twig', [
            'cible' => $cible,
            'l' => $l,
            'champs' => $champs,
            'erreurs' => $erreurs,
            'unites' => $unites,
            'categories' => $this->c->produits($this->portee($requete))->categories(),
            'taux_tva' => Tva::TAUX,
            'tva_habituelle' => $this->tvaHabituelle($requete, $l),
        ]), $code);
    }

    /**
     * Ce que la ligne prend sans exception (D11) : le taux de son produit s'il en a un (offre seulement : une ligne de
     * facture a copié le sien), sinon celui de l'entreprise ; null si l'entreprise n'est pas assujettie et que la ligne
     * n'a pas d'exception : la question ne se pose pas.
     *
     * @param LigneVue $l
     * @return array{taux: int, de: string}|null
     */
    private function tvaHabituelle(Request $requete, array $l): ?array
    {
        $entreprise = $this->c->reglages($this->portee($requete))->lire()->tvaPourDixMille;
        if ($entreprise === 0 && $l['tva_ligne'] === null) {
            return null;
        }
        if ($this->genre === self::OFFRE && $l['produit_id'] !== null) {
            $taux = $this->c->produits($this->portee($requete))->parId($l['produit_id'])['tva_pour_dix_mille'] ?? null;
            if ($taux !== null) {
                return ['taux' => $taux, 'de' => 'du produit'];
            }
        }
        return ['taux' => $entreprise, 'de' => "de l'entreprise"];
    }

    /**
     * @param Cible $cible
     * @param array<string, string> $champs
     * @param array<string, string> $erreurs
     */
    private function pageAjoutAvec(Request $requete, array $cible, string $q, array $champs, array $erreurs, int $code): Response
    {
        $depot = $this->c->produits($this->portee($requete));
        $tous = $depot->tous();
        $gardes = array_column(Recherche::filtrer($tous, $q), 'id', 'id');
        $produits = [];
        foreach ($tous as $p) {
            $produits[] = $p + ['texte' => Recherche::texte($p), 'visible' => isset($gardes[$p['id']])];
        }
        return new Response($this->c->twig()->render('ajout.html.twig', [
            'cible' => $cible,
            'q' => $q,
            'produits' => $produits,
            'trouves' => count($gardes),
            'champs' => $champs,
            'erreurs' => $erreurs,
            'unites' => Saisie::UNITES,
            'categories' => $depot->categories(),
        ]), $code);
    }
}
