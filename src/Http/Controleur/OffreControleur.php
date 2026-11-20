<?php

declare(strict_types=1);

namespace OffreGen\Http\Controleur;

use OffreGen\Catalogue\Recherche;
use OffreGen\Compte\Marque;
use OffreGen\Donnees\DepotOffres;
use OffreGen\Donnees\Portee;
use OffreGen\Http\Conteneur;
use OffreGen\Http\Flash;
use OffreGen\Http\PageIntrouvable;
use OffreGen\Http\Parametres;
use OffreGen\Offre\Chantier;
use OffreGen\Offre\Piece;
use OffreGen\Offre\Saisie;
use OffreGen\Offre\Totaux;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * L'écran d'une offre (lignes rangées par pièce ou par catégorie, totaux), son en-tête (titre, client),
 * et le document qu'on remet au client : aperçu à l'écran et PDF.
 *
 * @phpstan-import-type Offre from DepotOffres
 * @phpstan-import-type Ligne from DepotOffres
 */
final class OffreControleur
{
    /** Titre du groupe des lignes qui ne viennent pas du catalogue. */
    public const HORS_CATALOGUE = 'Hors catalogue';

    public function __construct(private readonly Conteneur $c)
    {
    }

    /** @param array<string, string> $parametres */
    public function afficher(Request $requete, array $parametres): Response
    {
        // Sur PC, la liste des offres en cours reste dans sa colonne (D12) ; le téléphone la cache.
        $portee = new Portee($requete->attributes->getInt('_artisan'));
        $offres = $this->c->offres($portee)->liste();
        [$totaux, $aRelire] = AccueilControleur::totauxEtARelire($this->c, $portee, $offres);
        $textes = [];
        foreach ($offres as $o) {
            $textes[$o['id']] = Recherche::normaliser($o['titre'] . ' ' . $o['client_nom'] . ' ' . $o['numero']);
        }
        return new Response($this->c->twig()->render('offre.html.twig', $this->avecFactures($requete, $this->donnees($requete, $parametres)) + [
            'liste_pc' => ['offres' => $offres, 'totaux' => $totaux, 'a_relire' => $aRelire, 'textes' => $textes],
            // La ligne de recherche au bas du tableau, sur PC (maquette 25, E7).
            'catalogue' => LigneControleur::catalogue($this->c, $portee),
        ]));
    }

    /**
     * L'objet de l'offre et son chantier. Le client se choisit dans sa fenêtre (ClientControleur::choixPourOffre).
     *
     * @param array<string, string> $parametres
     */
    public function entete(Request $requete, array $parametres): Response
    {
        $offre = $this->offre($requete, $parametres);
        return $this->formulaireEntete($offre, ['titre' => $offre['titre']] + Chantier::depuisOffre($offre)->versChamps(), [], 200);
    }

    /** @param array<string, string> $parametres */
    public function modifierEntete(Request $requete, array $parametres): Response
    {
        $offre = $this->offre($requete, $parametres);
        $champs = ['titre' => $requete->request->getString('titre')];
        foreach (array_keys((new Chantier())->versChamps()) as $champ) {
            $champs[$champ] = $requete->request->getString($champ);
        }
        $titre = Saisie::texte($champs['titre'], 190);
        [$chantier, $erreurs] = Chantier::depuisSaisie($champs);
        if ($titre === null) {
            $erreurs['titre'] = "Indiquez l'objet de l'offre (190 caractères au plus).";
        }
        if ($titre === null || $chantier === null) {
            return $this->formulaireEntete($offre, $champs, $erreurs, 422);
        }
        $this->offres($requete)->modifierTitre($offre['id'], $titre, $this->c->horloge()->now());
        $this->offres($requete)->modifierChantier($offre['id'], $chantier, $this->c->horloge()->now());
        Flash::dire($requete, 'info', 'Offre enregistrée');
        return new RedirectResponse('/offres/' . $offre['id'], 303);
    }

    /** @param array<string, string> $parametres */
    public function document(Request $requete, array $parametres): Response
    {
        return new Response($this->c->twig()->render('document.html.twig', $this->donnees($requete, $parametres)));
    }

    /**
     * « Générer l'offre » montre le PDF, tel que le client le recevra (Xavier, 2026-11-15, décision D7 A) : dessiné dans
     * l'application par PDF.js, livré avec elle, les boutons restent en bas.
     *
     * @param array<string, string> $parametres
     */
    public function apercu(Request $requete, array $parametres): Response
    {
        $offre = $this->offre($requete, $parametres);
        $base = '/offres/' . $offre['id'];
        return new Response($this->c->twig()->render('apercu_pdf.html.twig', [
            'pdfjs' => self::PDFJS,
            'titre' => 'Offre ' . $offre['numero'],
            'retour' => ['href' => $base, 'texte' => 'Offre'],
            'pdf' => $base . '/pdf',
            'fichier' => 'Offre-' . $offre['numero'] . '.pdf',
            'partage' => 'Offre n° ' . $offre['numero'] . ' · ' . $offre['titre'],
            'marquer' => $base . '/envoyee',
            'modifier' => $base,
            'facturer' => $base . '/facturer',
        ]));
    }

    /**
     * Le partage du téléphone a réussi (décision D8, 2026-11-15) : l'offre est notée envoyée. Le navigateur l'annonce
     * après le partage ; sans partage possible, le PDF se télécharge et rien n'est noté.
     *
     * @param array<string, string> $parametres
     */
    public function envoyee(Request $requete, array $parametres): Response
    {
        $offre = $this->offre($requete, $parametres);
        $this->offres($requete)->marquerEnvoyee($offre['id'], $this->c->horloge()->now());
        Flash::dire($requete, 'info', 'Offre ' . $offre['numero'] . ' envoyée');
        return new RedirectResponse('/offres/' . $offre['id'], 303);
    }

    /** Dossier de PDF.js dans public/js/vendor (version verrouillée, empreinte vérifiée : docs/DEPLOIEMENT.md). */
    public const PDFJS = '/js/vendor/pdfjs-6.5.438';

    /**
     * Le PDF du même document, prêt à envoyer ou à imprimer.
     *
     * @param array<string, string> $parametres
     */
    public function pdf(Request $requete, array $parametres): Response
    {
        $d = $this->donnees($requete, $parametres);
        $pdf = self::rendrePdf($this->c, $d);
        $nom = 'Offre-' . $d['offre']['numero'] . '.pdf';
        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $nom . '"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * Le PDF d'un document (offre ou facture) : le CSS du document et celui de la marque voyagent dedans ; dompdf ne
     * lit aucune adresse (ressources distantes coupées), donc le logo aussi.
     *
     * « Page n / N » : dompdf compte les pages au fil du rendu, mais ne connaît le total qu'à la fin. Un document d'une
     * page est juste au premier rendu ; un document plus long est rendu une seconde fois, avec son total.
     *
     * @param array<string, mixed> $d les données du document, avec « m » (Marque)
     */
    public static function rendrePdf(Conteneur $c, array $d): string
    {
        $pdf = $c->documentPdf();
        [$sortie, $pages] = $pdf->rendreEtCompter(self::htmlPdf($c, $d));
        return $pages === 1 ? $sortie : $pdf->rendre(self::htmlPdf($c, ['pages' => $pages] + $d));
    }

    /**
     * Le HTML que lit dompdf : feuille de style et logo en ligne (aucune ressource distante), images en data:.
     *
     * @param array<string, mixed> $d
     */
    public static function htmlPdf(Conteneur $c, array $d): string
    {
        $m = $d['m'];
        if (!$m instanceof Marque) {
            throw new \LogicException('Document sans marque.');
        }
        $d['css'] = $c->documentPdf()->policesCss() . (string) file_get_contents($c->racine() . '/public/css/document.css') . "\n" . $m->css();
        $logo = $m->logo === null ? null : $c->dossierLogos() . '/' . basename($m->logo);
        $d['logo_src'] = $logo !== null && is_file($logo) ? 'data:image/jpeg;base64,' . base64_encode((string) file_get_contents($logo)) : null;
        $d['pdf'] = true;
        return $c->twig()->render('document_pdf.html.twig', $d);
    }

    /**
     * Les factures nées de l'offre, pour les liens « Facture 2026-001 » de son écran.
     *
     * @param array{offre: Offre} $d
     * @return array{offre: Offre, factures: list<array<string, mixed>>}
     */
    private function avecFactures(Request $requete, array $d): array
    {
        return $d + ['factures' => $this->c->factures(new Portee($requete->attributes->getInt('_artisan')))->deOffre($d['offre']['id'])];
    }

    /**
     * Tout ce que montrent l'écran de l'offre et son document.
     *
     * @param array<string, string> $parametres
     * @return array{m: Marque, logo_src: string|null, offre: Offre, chantier: Chantier, groupes: list<array{nom: string, lignes: list<array{l: Ligne, total: int}>, total: int}>, totaux: array{lignes: list<int>, ht: int, tva: int, arrondi: int, ttc: int, par_taux: list<array{taux: int, ht: int, tva: int}>}, tva: int, r: \OffreGen\Compte\Reglages, valable_jusqu_au: string, manque: int, doute: int}
     */
    private function donnees(Request $requete, array $parametres): array
    {
        $portee = new Portee($requete->attributes->getInt('_artisan'));
        $offre = $this->offre($requete, $parametres);
        return $this->vueDocument($portee, $offre, $this->c->offres($portee)->lignes($offre['id']), $this->c->reglages($portee)->lireMarque());
    }

    /**
     * Le document d'une offre, dans la marque donnée : celle de l'artisan, ou un autre modèle pour l'aperçu des réglages.
     *
     * @param Offre $offre
     * @param list<Ligne> $lignes
     * @return array{m: Marque, logo_src: string|null, offre: Offre, chantier: Chantier, groupes: list<array{nom: string, lignes: list<array{l: Ligne, total: int}>, total: int}>, totaux: array{lignes: list<int>, ht: int, tva: int, arrondi: int, ttc: int, par_taux: list<array{taux: int, ht: int, tva: int}>}, tva: int, r: \OffreGen\Compte\Reglages, valable_jusqu_au: string, manque: int, doute: int}
     */
    public function vueDocument(Portee $portee, array $offre, array $lignes, Marque $m): array
    {
        $r = $this->c->reglages($portee)->lire();
        $totaux = Totaux::calculer($lignes, $r->tvaPourDixMille, $r->arrondiCinqCentimes);
        $date = new \DateTimeImmutable($offre['modifiee_le'] ?? $offre['cree_le'], new \DateTimeZone('UTC'));
        $valable = $date->setTimezone(new \DateTimeZone('Europe/Zurich'))->modify('+' . $r->validiteJours . ' days')->format('d.m.Y');
        return [
            'm' => $m,
            // Aperçu à l'écran : le logo par son adresse (la CSP refuse les images en data:) ; pdf() le remplace.
            'logo_src' => $m->logo === null ? null : '/reglages/logo?v=' . $m->logo,
            'offre' => $offre,
            'chantier' => Chantier::depuisOffre($offre),
            'groupes' => self::grouper($lignes, $totaux['lignes']),
            'totaux' => $totaux,
            'tva' => $r->tvaPourDixMille,
            'r' => $r,
            'valable_jusqu_au' => $valable,
            'manque' => count(array_filter($lignes, static fn (array $l): bool => $l['prix_centimes'] === 0)),
            'doute' => count(array_filter($lignes, static fn (array $l): bool => $l['prix_centimes'] !== 0 && $l['a_verifier'] === 1)),
        ];
    }

    /**
     * Groupes dans l'ordre où leur première ligne paraît dans l'offre, donc dans l'ordre de la dictée.
     * Par pièce dès qu'une ligne en a une, « Général » en tête ; sinon par catégorie du catalogue (sous-chantier 28,
     * décisions P1 A et P2 A de Xavier, 2026-11-17). Sert aussi aux lignes d'une facture, qui n'ont pas de pièce.
     *
     * @template T of array{categorie: string|null, piece?: string|null}
     * @param list<T> $lignes
     * @param list<int> $totauxLignes total de chaque ligne, dans le même ordre
     * @return list<array{nom: string, lignes: list<array{l: T, total: int}>, total: int, piece: string|null, par_piece: bool}>
     */
    public static function grouper(array $lignes, array $totauxLignes): array
    {
        $parPiece = false;
        $general = false;
        foreach ($lignes as $l) {
            $piece = $l['piece'] ?? null;
            $parPiece = $parPiece || $piece !== null;
            $general = $general || $piece === null;
        }
        $groupes = [];
        if ($parPiece && $general) {
            $groupes[''] = ['nom' => Piece::GENERAL, 'lignes' => [], 'total' => 0, 'piece' => null, 'par_piece' => true];
        }
        foreach ($lignes as $i => $l) {
            if ($parPiece) {
                $piece = $l['piece'] ?? null;
                $cle = $piece === null ? '' : 'p:' . Piece::cle($piece);
                $nom = $piece ?? Piece::GENERAL;
            } else {
                // Lire la catégorie à part : un « ?? » sur $l resserrerait son type et perdrait T.
                $categorie = $l['categorie'];
                $nom = $categorie ?? self::HORS_CATALOGUE;
                $cle = 'c:' . $nom;
                $piece = null;
            }
            $groupes[$cle] ??= ['nom' => $nom, 'lignes' => [], 'total' => 0, 'piece' => $piece, 'par_piece' => $parPiece];
            $groupes[$cle]['lignes'][] = ['l' => $l, 'total' => $totauxLignes[$i] ?? 0];
            $groupes[$cle]['total'] += $totauxLignes[$i] ?? 0;
        }
        return array_values($groupes);
    }

    private function offres(Request $requete): DepotOffres
    {
        return $this->c->offres(new Portee($requete->attributes->getInt('_artisan')));
    }

    /**
     * @param array<string, string> $parametres
     * @return Offre
     */
    private function offre(Request $requete, array $parametres): array
    {
        return $this->offres($requete)->parId(Parametres::id($parametres, 'id')) ?? throw new PageIntrouvable();
    }

    /**
     * @param Offre $offre
     * @param array<string, string> $champs
     * @param array<string, string> $erreurs
     */
    private function formulaireEntete(array $offre, array $champs, array $erreurs, int $code): Response
    {
        return new Response($this->c->twig()->render('offre_entete.html.twig', ['offre' => $offre, 'champs' => $champs, 'erreurs' => $erreurs]), $code);
    }
}
