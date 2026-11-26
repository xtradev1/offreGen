<?php

declare(strict_types=1);

namespace OffreGen\Http\Controleur;

use OffreGen\Catalogue\Recherche;
use OffreGen\Catalogue\SaisieProduit;
use OffreGen\Donnees\DepotOffres;
use OffreGen\Donnees\Portee;
use OffreGen\Http\Conflit;
use OffreGen\Http\Conteneur;
use OffreGen\Http\Flash;
use OffreGen\Http\Formats;
use OffreGen\Http\PageIntrouvable;
use OffreGen\Http\Parametres;
use OffreGen\Offre\Saisie;
use OffreGen\Offre\SaisieRefusee;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Une ligne d'offre et le catalogue (décision D6 de Xavier, maquette 24, 2026-11-15).
 *
 * - « Créer le produit » : une ligne hors catalogue devient un produit (désignation, unité, prix de la ligne, catégorie
 *   « Divers »), et la ligne s'y rattache. Un toucher.
 * - « Rattacher à un produit » : la ligne devient ce produit (désignation, unité, prix du catalogue, comme l'extraction
 *   le fait), sa quantité reste. Si la ligne était hors catalogue, le nom que l'IA lui avait donné devient un synonyme du
 *   produit : la prochaine dictée le reconnaîtra. Une ligne déjà au catalogue peut changer de produit (l'IA s'est
 *   trompée) sans rien ajouter aux synonymes : son nom est celui d'un autre produit.
 * - Prix dicté (Xavier, 2026-11-17) : la ligne garde le prix du catalogue ; « Prendre » met le prix dicté, puis
 *   « Mettre aussi … au catalogue ? » : oui change le produit, non le laisse. Dans les deux cas, la note disparaît.
 *
 * @phpstan-import-type Ligne from DepotOffres
 */
final class LigneCatalogueControleur
{
    public const CATEGORIE = 'Divers';

    public function __construct(private readonly Conteneur $c)
    {
    }

    /** @param array<string, string> $parametres */
    public function creerProduit(Request $requete, array $parametres): Response
    {
        [$offre, $l] = $this->ligne($requete, $parametres);
        if ($l['produit_id'] !== null) {
            throw new Conflit('Cette ligne est déjà au catalogue.');
        }
        // Depuis la fiche « Mettre au catalogue » (4b) : les champs de l'artisan, catégorie comprise. Sans eux : la ligne
        // telle quelle, en « Divers » (bouton d'avant, encore servi sans JavaScript ni fiche).
        $r = $requete->request;
        if ($r->has('designation')) {
            $champs = ['designation' => $r->getString('designation'), 'unite' => $r->getString('unite'), 'prix' => $r->getString('prix'),
                'categorie' => $r->getString('categorie'), 'nouvelle_categorie' => $r->getString('nouvelle_categorie'), 'synonymes' => ''];
            try {
                $produit = SaisieProduit::lire($champs, $l['unite']);
            } catch (SaisieRefusee $e) {
                return $this->vueFiche($requete, $offre, $l, $champs, $e->erreurs, 422, 'nouveau');
            }
        } else {
            $produit = SaisieProduit::lire(['designation' => $l['designation'], 'unite' => $l['unite'],
                'prix' => $l['prix_centimes'] === 0 ? '' : Formats::saisiePrix($l['prix_centimes']),
                'categorie' => self::CATEGORIE, 'nouvelle_categorie' => '', 'synonymes' => ''], $l['unite']);
        }
        $id = $this->c->produits($this->portee($requete))->creer($produit);
        $offres = $this->offres($requete);
        $offres->rattacherProduit($offre['id'], $l['id'], $id);
        $offres->marquerModifiee($offre['id'], $this->c->horloge()->now());
        Flash::dire($requete, 'info', Flash::cite($produit['designation']) . ' ajouté au catalogue');
        return new RedirectResponse('/offres/' . $offre['id'] . '#l' . $l['id'], 303);
    }

    /**
     * La fiche « Mettre au catalogue » d'une ligne hors catalogue (4a et 4b, 2026-11-20).
     *
     * @param array<string, string> $parametres
     */
    public function fiche(Request $requete, array $parametres): Response
    {
        // Ouverte aussi pour une ligne déjà rangée (changer de produit, comme l'écran Rattacher) ; « Nouveau produit »
        // y répond 409 (creerProduit).
        [$offre, $l] = $this->ligne($requete, $parametres);
        $onglet = $requete->query->getString('onglet');
        return $this->vueFiche($requete, $offre, $l, null, [], 200, in_array($onglet, ['nouveau', 'existant'], true) ? $onglet : null);
    }

    /**
     * @param array<string, mixed> $offre
     * @param array<string, mixed> $l
     * @param array<string, string>|null $champs null : repris de la ligne
     * @param array<string, string> $erreurs
     * @param string|null $onglet null : « existant » si la recherche trouve un produit, sinon « nouveau »
     */
    private function vueFiche(Request $requete, array $offre, array $l, ?array $champs, array $erreurs, int $code, ?string $onglet): Response
    {
        $depot = $this->c->produits($this->portee($requete));
        $tous = $depot->tous();
        $designation = (string) $l['designation'];
        $q = $requete->query->has('q') ? $requete->query->getString('q') : self::premierMot($designation);
        $gardes = array_column(Recherche::filtrer($tous, $q), 'id', 'id');
        $produits = [];
        foreach ($tous as $p) {
            $produits[] = $p + ['texte' => Recherche::texte($p), 'visible' => $gardes === [] || isset($gardes[$p['id']])];
        }
        $categories = $depot->categories();
        $unites = Saisie::UNITES;
        if (!in_array($l['unite'], $unites, true)) {
            $unites[] = (string) $l['unite'];
        }
        $prix = (int) $l['prix_centimes'];
        $champs ??= ['designation' => $designation, 'unite' => (string) $l['unite'], 'prix' => $prix === 0 ? '' : Formats::saisiePrix($prix),
            'categorie' => in_array(self::CATEGORIE, $categories, true) ? self::CATEGORIE : ($categories[0] ?? ''),
            'nouvelle_categorie' => $categories === [] ? self::CATEGORIE : ''];
        return new Response($this->c->twig()->render('ligne_catalogue.html.twig', [
            'offre' => $offre, 'l' => $l, 'q' => $q, 'produits' => $produits, 'aucun' => $gardes === [],
            'onglet' => $onglet ?? ($tous !== [] && $gardes !== [] ? 'existant' : 'nouveau'),
            'champs' => $champs, 'erreurs' => $erreurs, 'categories' => $categories, 'unites' => $unites,
        ]), $code);
    }

    /** @param array<string, string> $parametres */
    public function choix(Request $requete, array $parametres): Response
    {
        [$offre, $l] = $this->ligne($requete, $parametres);
        $tous = $this->c->produits($this->portee($requete))->tous();
        $q = $requete->query->has('q') ? $requete->query->getString('q') : self::premierMot($l['designation']);
        $gardes = array_column(Recherche::filtrer($tous, $q), 'id', 'id');
        $produits = [];
        foreach ($tous as $p) {
            if ($p['id'] !== $l['produit_id']) {
                $produits[] = $p + ['texte' => Recherche::texte($p), 'visible' => $gardes === [] || isset($gardes[$p['id']])];
            }
        }
        return new Response($this->c->twig()->render('rattacher.html.twig', [
            'offre' => $offre,
            'l' => $l,
            'q' => $q,
            'produits' => $produits,
            'aucun' => $gardes === [],
        ]));
    }

    /** @param array<string, string> $parametres */
    public function rattacher(Request $requete, array $parametres): Response
    {
        [$offre, $l] = $this->ligne($requete, $parametres);
        $catalogue = $this->c->produits($this->portee($requete));
        $p = $catalogue->parId(Parametres::id(['produit' => $requete->request->getString('produit')], 'produit')) ?? throw new PageIntrouvable();
        $offres = $this->offres($requete);
        $offres->corrigerLigne($offre['id'], $l['id'], $p['designation'], $l['quantite_milliemes'], $p['unite'], $p['prix_centimes']);
        $offres->rattacherProduit($offre['id'], $l['id'], $p['id']);
        // 4b (2026-11-20) : un autre prix au catalogue ; la ligne garde le sien en note et passe à vérifier, en jaune.
        if ($l['prix_centimes'] !== 0 && $l['prix_centimes'] !== $p['prix_centimes']) {
            $offres->garderPrixDeLaLigne($offre['id'], $l['id'], $l['prix_centimes']);
        }
        if ($l['produit_id'] === null && mb_strtolower($l['designation']) !== mb_strtolower($p['designation'])) {
            $synonymes = SaisieProduit::synonymes(($p['synonymes'] ?? '') . ', ' . $l['designation']);
            if (mb_strlen($synonymes) <= SaisieProduit::SYNONYMES_MAX) {
                // Le produit entier repasse, taux de TVA compris : modifier() remet à NULL ce qu'on ne lui donne pas.
                $catalogue->modifier($p['id'], ['synonymes' => $synonymes] + $p);
            }
        }
        $offres->marquerModifiee($offre['id'], $this->c->horloge()->now());
        Flash::dire($requete, 'info', 'Ligne rattachée à ' . Flash::cite($p['designation']));
        return new RedirectResponse('/offres/' . $offre['id'] . '#l' . $l['id'], 303);
    }

    /** @param array<string, string> $parametres */
    public function prendrePrixDicte(Request $requete, array $parametres): Response
    {
        [$offre, $l] = $this->ligne($requete, $parametres);
        $offres = $this->offres($requete);
        if ($l['prix_dicte_centimes'] === null || !$offres->prendrePrixDicte($offre['id'], $l['id'])) {
            throw new Conflit('Aucun prix dicté à prendre sur cette ligne.');
        }
        $offres->marquerModifiee($offre['id'], $this->c->horloge()->now());
        return new RedirectResponse('/offres/' . $offre['id'] . '#l' . $l['id'], 303);
    }

    /** @param array<string, string> $parametres */
    public function prixDicteAuCatalogue(Request $requete, array $parametres): Response
    {
        [$offre, $l] = $this->ligne($requete, $parametres);
        if ($l['prix_dicte_centimes'] === null) {
            throw new Conflit('Aucun prix dicté sur cette ligne.');
        }
        if ($requete->request->getString('choix') === 'oui') {
            $catalogue = $this->c->produits($this->portee($requete));
            $p = $l['produit_id'] === null ? null : $catalogue->parId($l['produit_id']);
            if ($p === null) {
                throw new Conflit('Cette ligne n\'est plus rattachée à un produit du catalogue.');
            }
            $catalogue->modifier($p['id'], ['prix_centimes' => $l['prix_dicte_centimes']] + $p);
            Flash::dire($requete, 'info', Flash::cite($p['designation']) . "\u{00A0}: " . Formats::montant($l['prix_dicte_centimes']) . ' au catalogue');
        }
        $this->offres($requete)->oublierPrixDicte($offre['id'], $l['id']);
        return new RedirectResponse('/offres/' . $offre['id'] . '#l' . $l['id'], 303);
    }

    /** Le premier mot de trois lettres au moins : « Crépi de soubassement » cherche « Crépi ». */
    private static function premierMot(string $designation): string
    {
        foreach (preg_split('/[\s,;.\/()-]+/u', $designation) ?: [] as $mot) {
            if (mb_strlen($mot) >= 3) {
                return $mot;
            }
        }
        return '';
    }

    private function portee(Request $requete): Portee
    {
        return new Portee($requete->attributes->getInt('_artisan'));
    }

    private function offres(Request $requete): DepotOffres
    {
        return $this->c->offres($this->portee($requete));
    }

    /**
     * @param array<string, string> $parametres
     * @return array{array<string, mixed>&array{id: int}, Ligne}
     */
    private function ligne(Request $requete, array $parametres): array
    {
        $offres = $this->offres($requete);
        $offre = $offres->parId(Parametres::id($parametres, 'id')) ?? throw new PageIntrouvable();
        $l = $offres->ligne($offre['id'], Parametres::id($parametres, 'ligne')) ?? throw new PageIntrouvable();
        return [$offre, $l];
    }
}
