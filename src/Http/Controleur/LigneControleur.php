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
use OffreGen\Offre\Offerte;
use OffreGen\Offre\Options;
use OffreGen\Offre\Piece;
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
 * @phpstan-type LigneVue array{id: int, designation: string, quantite_milliemes: int, unite: string, prix_centimes: int, offerte: int, produit_id: int|null, categorie: string|null, tva_pour_dix_mille: int|null, tva_ligne: int|null}
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
                   'prix' => Formats::saisiePrixLigne($l), 'categorie' => '', 'nouvelle_categorie' => '',
                   'tva' => $l['tva_ligne'] === null ? '' : (string) $l['tva_ligne'],
                   'offerte' => Offerte::est($l) ? Offerte::CHOIX_OFFERTE : Offerte::CHOIX_FACTUREE,
                   // Une ligne de facture n'a pas de pièce ; celle d'une offre se lit dans le dépôt des offres.
                   'piece' => $this->genre === self::OFFRE ? ($this->c->offres($this->portee($requete))->ligne($cible['id'], $l['id'])['piece'] ?? '') : ''];
        $champs += $this->genre === self::OFFRE ? $this->natureActuelle($requete, $cible['id'], $l['id']) : ['nature' => Options::LIGNE, 'remplace' => ''];
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
            $erreurs['prix'] = "Prix en francs, par exemple 28 ou 28.50\u{00A0}; 0 pour l'offrir.";
        }
        // « Facturée / Offerte » vient de la fenêtre de la ligne seulement ; une autre valeur est ignorée.
        $choix = $r->has('offerte') && in_array($r->getString('offerte'), [Offerte::CHOIX_OFFERTE, Offerte::CHOIX_FACTUREE], true)
            ? $r->getString('offerte') : null;
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

        // Options et variantes (O4, E3), depuis la fenêtre de la ligne d'une offre ; une facture n'en a pas.
        $actuelle = $this->genre === self::OFFRE ? $this->natureActuelle($requete, $cible['id'], $l['id']) : null;
        $remplace = ctype_digit($r->getString('remplace')) ? (int) $r->getString('remplace') : null;
        // Seulement un vrai changement : une variante dont la ligne est supprimée se montre « option » ; la renvoyer telle
        // quelle ne la détache pas de sa ligne, qu'« Annuler » remettra (revue, C1 ; R6 : pas d'écriture pour rien).
        $change = $actuelle !== null && $r->has('nature') && ($r->getString('nature') !== $actuelle['nature']
            || ($r->getString('nature') === Options::VARIANTE && (string) $remplace !== $actuelle['remplace']));
        if ($change) {
            if (!$this->c->offres($this->portee($requete))->changerNature($cible['id'], $l['id'], $r->getString('nature'), $remplace)) {
                $erreur = "Une variante remplace une ligne du total, une autre que celle-ci\u{00A0}; une ligne qui a des variantes reste dans le total.";
                if ($depuisPage) {
                    return $this->formulaire($requete, $cible, $l, self::champs($requete), ['nature' => $erreur], 422);
                }
                Flash::dire($requete, 'erreur', $erreur . ' Rien n\'a été modifié.');
                return $this->versLaLigne($cible, $l['id']);
            }
        }
        $support = $this->support($requete);
        $support->corrigerLigne($cible['id'], $l['id'], $designation, $quantite, $unite, $prix);
        $offerte = Offerte::apresSaisie(Offerte::est($l), $l['prix_centimes'], $r->has('prix') ? $r->getString('prix') : null, $prix, $choix);
        if ($offerte !== Offerte::est($l)) {
            $support->changerOfferte($cible['id'], $l['id'], $offerte);
        }
        if ($tva !== $l['tva_ligne']) {
            $support->changerTauxLigne($cible['id'], $l['id'], $tva);
        }
        // Déplacer une ligne d'offre dans une autre pièce (sous-chantier 28, P5), depuis sa fiche.
        if ($this->genre === self::OFFRE && $r->has('piece')) {
            $this->c->offres($this->portee($requete))->changerPiece($cible['id'], $l['id'], Piece::lire($r->getString('piece')));
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

    /**
     * Le client retient une option ou une variante, ou y renonce (O5) ; retenir une variante décoche les autres de sa
     * ligne. « retour=facturer » : la feuille « Facturer » reprend ces cases (E1 A) et y revient. Offre seulement.
     *
     * @param array<string, string> $parametres
     */
    public function retenir(Request $requete, array $parametres): Response
    {
        if ($this->genre !== self::OFFRE) {
            throw new PageIntrouvable();
        }
        [$cible, $l] = $this->ligne($requete, $parametres);
        $valeur = $requete->request->getString('retenue');
        if (!in_array($valeur, ['0', '1'], true)) {
            throw new Conflit("Choix illisible\u{00A0}: rien n'a été modifié.");
        }
        if (!$this->c->offres($this->portee($requete))->retenir($cible['id'], $l['id'], $valeur === '1')) {
            throw new Conflit("Cette ligne est dans le total\u{00A0}: seule une option ou une variante se retient.");
        }
        $this->modifiee($requete, $cible['id']);
        if ($requete->request->getString('retour') === 'facturer') {
            return new RedirectResponse($cible['base'] . '/facturer', 303);
        }
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
        $piece = $requete->query->has('piece') ? Piece::lire($requete->query->getString('piece')) : null;
        [$nature, $base] = $this->natureDeLAjout($requete, $cible['id'], $requete->query->getString('nature'), $requete->query->getString('remplace'), $piece);
        // Une variante part de la quantité et de l'unité de la ligne qu'elle remplace (V3, 2026-11-24).
        $champs = ['designation' => $q, 'quantite' => $base === null ? '1' : self::reprise($requete->query->getString('quantite'), $base),
            'unite' => $base === null ? 'forfait' : $base['unite'], 'prix' => '', 'categorie' => '', 'nouvelle_categorie' => '', 'tva' => ''];
        return $this->pageAjoutAvec($requete, $cible, $q, $champs, [], 200, $nature, $base);
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
        // Sous-chantier 28 (P4) : la pièce choisie dans la feuille ; sans pièce dite (ajout rapide au clavier), celle de
        // la dernière ligne. Rien pour une facture.
        $piece = $this->genre !== self::OFFRE ? null
            : ($r->has('piece') ? Piece::lire($r->getString('piece')) : $this->piecesDeLOffre($requete, $cible['id'])['derniere']);
        $suite = $r->has('piece') ? '&piece=' . rawurlencode($r->getString('piece')) : '';
        [$nature, $base] = $this->natureDeLAjout($requete, $cible['id'], $r->getString('nature'), $r->getString('remplace'));
        if ($nature !== Options::LIGNE) {
            $suite .= '&nature=' . $nature . ($base === null ? '' : '&remplace=' . $base['id']);
        }
        // La quantité d'une variante (V2 A) : le champ « reprise » de chaque ajout, tapé en haut de la feuille ; illisible ou
        // absent, celle de la ligne remplacée. Elle reste proposée à l'ajout suivant. La quantité d'une ligne libre
        // détaillée est la sienne et ne la remplace pas.
        $reprise = $base === null ? null : (Saisie::milliemes($r->getString('reprise')) ?? $base['quantite_milliemes']);
        if ($reprise !== null) {
            $suite .= '&quantite=' . rawurlencode(Formats::saisieQuantite($reprise));
        }

        if ($r->has('produit')) {
            $p = $produits->parId(Parametres::id(['p' => $r->getString('produit')], 'p')) ?? throw new PageIntrouvable();
            // Une variante prend cette quantité et l'unité de la ligne qu'elle remplace ; le prix est celui du produit (V1 A,
            // V2 A de Xavier, 2026-11-24, à la place de C6 de la revue des options). D'une autre unité que le produit, elle
            // passe dans « Relire » : 42 m² au prix de la pièce ne font pas un total juste.
            $quantite = $reprise ?? 1000;
            $unite = $base === null ? $p['unite'] : $base['unite'];
            $id = $support->ajouterLigne($cible['id'], $p['designation'], $quantite, $unite, $p['prix_centimes'], $p['id']);
            $this->rangerDansLaPiece($requete, $cible['id'], $id, $base !== null ? $base['piece'] : $piece);
            $this->poserNature($requete, $cible['id'], $id, $nature, $base);
            if ($base !== null && $id > 0 && $p['unite'] !== $base['unite']) {
                $this->c->offres($this->portee($requete))->marquerAVerifier($cible['id'], $id);
            }
            $this->modifiee($requete, $cible['id']);
            if ($r->getString('reste') === '1') {
                return self::resterSurAjouter($cible, $id, $suite);
            }
            Flash::dire($requete, 'focus', $id);
            return $this->versLaLigne($cible, $id);
        }

        $champs = self::champs($requete);
        // Ligne tapée sans quantité : celle de la variante ; si la ligne remplacée a disparu depuis l'affichage (la ligne
        // entre en option), 1, comme une ligne tapée hors variante.
        if (trim($champs['quantite']) === '' && ($reprise !== null || $r->has('reprise'))) {
            $champs['quantite'] = $reprise !== null ? Formats::saisieQuantite($reprise) : '1';
        }
        $erreurs = [];
        $designation = Saisie::texte($champs['designation'], 255);
        if ($designation === null) {
            $erreurs['designation'] = 'Indiquez la désignation (255 caractères au plus).';
        }
        $quantite = Saisie::milliemes($champs['quantite']);
        if ($quantite === null) {
            $erreurs['quantite'] = 'Quantité plus grande que 0, par exemple 1 ou 12.5.';
        }
        // Une variante peut garder l'unité de sa ligne, même hors de la liste (une unité dictée).
        $unite = Saisie::unite($champs['unite'], $base['unite'] ?? null);
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
                $produit = SaisieProduit::lire($champs + ['synonymes' => ''], $base['unite'] ?? null);
            } catch (SaisieRefusee $e) {
                $erreurs += $e->erreurs;
            }
        }
        if ($designation === null || $quantite === null || $unite === null || $prix === null || $erreurs !== []) {
            return $this->pageAjoutAvec($requete, $cible, '', $champs, $erreurs, 422, $nature, $base);
        }
        $produitId = $produit === null ? null : $produits->creer($produit);
        $id = $support->ajouterLigne($cible['id'], $designation, $quantite, $unite, $prix, $produitId);
        if (Offerte::apresSaisie(false, 0, $champs['prix'], $prix, null)) {
            $support->changerOfferte($cible['id'], $id, true);
        }
        $this->rangerDansLaPiece($requete, $cible['id'], $id, $base !== null ? $base['piece'] : $piece);
        $this->poserNature($requete, $cible['id'], $id, $nature, $base);
        $this->modifiee($requete, $cible['id']);
        if ($produit !== null) {
            Flash::dire($requete, 'info', Flash::cite($produit['designation']) . ' ajouté à ' . $cible['document'] . ' et au catalogue');
        }
        if ($r->getString('reste') === '1') {
            return self::resterSurAjouter($cible, $id, $suite);
        }
        return $this->versLaLigne($cible, $id);
    }

    /**
     * La nature de la ligne telle que l'artisan la voit (une variante sans ligne de base est une option, O3) et la ligne
     * qu'elle remplace.
     *
     * @return array{nature: string, remplace: string}
     */
    private function natureActuelle(Request $requete, int $offreId, int $ligneId): array
    {
        $lignes = $this->c->offres($this->portee($requete))->lignes($offreId);
        foreach ($lignes as $x) {
            if ($x['id'] === $ligneId) {
                $nature = Options::natureEffective($x, $lignes);
                return ['nature' => $nature, 'remplace' => $nature === Options::VARIANTE ? (string) $x['remplace_id'] : ''];
            }
        }
        return ['nature' => Options::LIGNE, 'remplace' => ''];
    }

    /**
     * La nature d'une ligne ajoutée à une offre (O4) et, pour une variante, la ligne remplacée : celle demandée si c'est une
     * ligne du total de l'offre, sinon la dernière ligne du total de la pièce choisie, sinon la dernière de l'offre.
     * Une facture n'a que des lignes.
     *
     * @return array{string, array{id: int, designation: string, quantite_milliemes: int, unite: string, piece: string|null}|null}
     */
    private function natureDeLAjout(Request $requete, int $offreId, string $nature, string $remplace, ?string $piece = null): array
    {
        if ($this->genre !== self::OFFRE || !in_array($nature, [Options::OPTION, Options::VARIANTE], true)) {
            return [Options::LIGNE, null];
        }
        if ($nature === Options::OPTION) {
            return [Options::OPTION, null];
        }
        $bases = $this->bases($requete, $offreId);
        $voulue = ctype_digit($remplace) ? (int) $remplace : 0;
        foreach ($bases as $b) {
            if ($b['id'] === $voulue) {
                return [Options::VARIANTE, $b];
            }
        }
        // Demandée puis disparue entre l'affichage et l'envoi : rien à remplacer, la ligne entre en option (O3 ; revue, C7).
        if ($voulue > 0) {
            return [Options::OPTION, null];
        }
        $dansLaPiece = array_values(array_filter($bases, static fn (array $b): bool => $piece !== null && $b['piece'] !== null && Piece::cle($b['piece']) === Piece::cle($piece)));
        $base = $dansLaPiece[count($dansLaPiece) - 1] ?? $bases[count($bases) - 1] ?? null;
        // Aucune ligne à remplacer : la variante n'a pas de sens, la ligne entre comme option (O3).
        return $base === null ? [Options::OPTION, null] : [Options::VARIANTE, $base];
    }

    /**
     * Les lignes du total d'une offre, que peut remplacer une variante.
     *
     * @return list<array{id: int, designation: string, quantite_milliemes: int, unite: string, piece: string|null}>
     */
    private function bases(Request $requete, int $offreId): array
    {
        $bases = [];
        foreach ($this->c->offres($this->portee($requete))->lignes($offreId) as $l) {
            if ($l['nature'] === Options::LIGNE) {
                $bases[] = ['id' => $l['id'], 'designation' => $l['designation'], 'quantite_milliemes' => $l['quantite_milliemes'], 'unite' => $l['unite'], 'piece' => $l['piece']];
            }
        }
        return $bases;
    }

    /** @param array{id: int, designation: string, quantite_milliemes: int, unite: string, piece: string|null}|null $base */
    private function poserNature(Request $requete, int $offreId, int $ligneId, string $nature, ?array $base): void
    {
        if ($nature !== Options::LIGNE && $ligneId > 0) {
            $this->c->offres($this->portee($requete))->changerNature($offreId, $ligneId, $nature, $base['id'] ?? null);
        }
    }

    private function rangerDansLaPiece(Request $requete, int $offreId, int $ligneId, ?string $piece): void
    {
        if ($this->genre === self::OFFRE && $piece !== null) {
            $this->c->offres($this->portee($requete))->changerPiece($offreId, $ligneId, $piece);
        }
    }

    /**
     * Les pièces d'une offre, dans l'ordre de leur première ligne, sous leur premier nom (« chambre » est « Chambre »),
     * et la pièce de la dernière ligne (null : « Général »). Liste vide : l'offre n'est pas rangée par pièce.
     *
     * @return array{pieces: list<string>, derniere: string|null}
     */
    private function piecesDeLOffre(Request $requete, int $offreId): array
    {
        $pieces = [];
        $derniere = null;
        foreach ($this->c->offres($this->portee($requete))->lignes($offreId) as $l) {
            $derniere = $l['piece'];
            if ($l['piece'] !== null) {
                $pieces[Piece::cle($l['piece'])] ??= $l['piece'];
            }
        }
        return ['pieces' => array_values($pieces), 'derniere' => $derniere === null ? null : $pieces[Piece::cle($derniere)]];
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

    /**
     * Ajouter dans la feuille (E7, maquette 25) : la feuille reste ouverte pour la ligne suivante, et dit laquelle vient
     * d'entrer.
     *
     * @param Cible $cible
     */
    private static function resterSurAjouter(array $cible, int $ligneId, string $suite = ''): RedirectResponse
    {
        return new RedirectResponse($cible['base'] . '/ajouter?ajoutee=' . $ligneId . $suite, 303);
    }

    /**
     * Le catalogue pour la ligne de recherche au bas du tableau, sur PC (E7) : chaque produit et son texte cherché.
     *
     * @return list<array{id: int, designation: string, unite: string, prix_centimes: int, texte: string}>
     */
    public static function catalogue(Conteneur $c, Portee $portee): array
    {
        return array_map(static fn (array $p): array => ['id' => $p['id'], 'designation' => $p['designation'], 'unite' => $p['unite'],
            'prix_centimes' => $p['prix_centimes'], 'texte' => Recherche::texte($p)], $c->produits($portee)->tous());
    }

    /** @param Cible $cible */
    private function versLaLigne(array $cible, int $ligneId): RedirectResponse
    {
        return new RedirectResponse($cible['base'] . '#l' . $ligneId, 303);
    }

    /** @return array{designation: string, quantite: string, unite: string, prix: string, categorie: string, nouvelle_categorie: string, tva: string, piece: string, offerte: string, nature: string, remplace: string} */
    private static function champs(Request $requete): array
    {
        $r = $requete->request;
        return ['designation' => $r->getString('designation'), 'quantite' => $r->getString('quantite'), 'unite' => $r->getString('unite'),
                'prix' => $r->getString('prix'), 'categorie' => $r->getString('categorie'), 'nouvelle_categorie' => $r->getString('nouvelle_categorie'),
                'tva' => $r->getString('tva'), 'piece' => $r->getString('piece'),
                'offerte' => $r->getString('offerte') === Offerte::CHOIX_OFFERTE ? Offerte::CHOIX_OFFERTE : Offerte::CHOIX_FACTUREE,
                'nature' => in_array($r->getString('nature'), Options::NATURES, true) ? $r->getString('nature') : Options::LIGNE, 'remplace' => $r->getString('remplace')];
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
            'pieces' => $this->genre === self::OFFRE ? $this->piecesDeLOffre($requete, $cible['id'])['pieces'] : [],
            'bases' => $this->genre === self::OFFRE ? array_values(array_filter($this->bases($requete, $cible['id']), static fn (array $b): bool => $b['id'] !== $l['id'])) : [],
            'a_des_variantes' => $this->genre === self::OFFRE && $this->c->offres($this->portee($requete))->aDesVariantes($cible['id'], $l['id']),
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
     * La quantité d'une variante, comme l'artisan la tape : la sienne si elle se lit, sinon celle de la ligne remplacée.
     *
     * @param array{id: int, designation: string, quantite_milliemes: int, unite: string, piece: string|null} $base
     */
    private static function reprise(string $saisie, array $base): string
    {
        return Formats::saisieQuantite(Saisie::milliemes($saisie) ?? $base['quantite_milliemes']);
    }

    /**
     * @param Cible $cible
     * @param array<string, string> $champs
     * @param array<string, string> $erreurs
     * @param array{id: int, designation: string, quantite_milliemes: int, unite: string, piece: string|null}|null $base
     */
    private function pageAjoutAvec(Request $requete, array $cible, string $q, array $champs, array $erreurs, int $code, string $nature = Options::LIGNE, ?array $base = null): Response
    {
        $depot = $this->c->produits($this->portee($requete));
        $tous = $depot->tous();
        $gardes = array_column(Recherche::filtrer($tous, $q), 'id', 'id');
        $produits = [];
        foreach ($tous as $p) {
            $produits[] = $p + ['texte' => Recherche::texte($p), 'visible' => isset($gardes[$p['id']])];
        }
        // La ligne qui vient d'entrer, annoncée dans la feuille ; une ligne d'ailleurs ne s'annonce pas.
        $ajoutee = $requete->query->getInt('ajoutee');
        // Sous-chantier 28 (P4) : une offre rangée par pièce propose ses pièces, « Général » et une autre pièce ; la
        // pièce de la dernière ligne est choisie d'avance. Des liens : la page marche sans JavaScript.
        $pieces = [];
        $choisie = '';
        if ($this->genre === self::OFFRE) {
            $p = $this->piecesDeLOffre($requete, $cible['id']);
            $pieces = $p['pieces'];
            $choisie = $requete->query->has('piece') ? (Piece::lire($requete->query->getString('piece')) ?? '') : ($p['derniere'] ?? '');
            if ($choisie !== '' && !in_array(Piece::cle($choisie), array_map(Piece::cle(...), $pieces), true)) {
                $pieces[] = $choisie;
            }
        }
        $ligne = $ajoutee > 0 ? $this->support($requete)->ligne($cible['id'], $ajoutee) : null;
        $unites = $base !== null && !in_array($base['unite'], Saisie::UNITES, true) ? [...Saisie::UNITES, $base['unite']] : Saisie::UNITES;
        return new Response($this->c->twig()->render('ajout.html.twig', [
            'cible' => $cible,
            'ajoutee' => $ligne === null ? null : $ligne['designation'],
            'q' => $q,
            'produits' => $produits,
            'trouves' => count($gardes),
            'champs' => $champs,
            'erreurs' => $erreurs,
            'unites' => $unites,
            'categories' => $depot->categories(),
            'pieces' => $pieces,
            'piece_choisie' => $choisie,
            'nature' => $nature,
            'base' => $base,
            // La page de l'adresse (?quantite=), ou le formulaire refusé (422) : la quantité de la variante ne se perd pas.
            'reprise' => $base === null ? null : self::reprise($requete->query->has('quantite') ? $requete->query->getString('quantite') : $requete->request->getString('reprise'), $base),
            'bases' => $this->genre === self::OFFRE ? $this->bases($requete, $cible['id']) : [],
        ]), $code);
    }
}
