<?php

declare(strict_types=1);

namespace OffreGen\Http\Controleur;

use OffreGen\Compte\Marque;
use OffreGen\Donnees\DepotOffres;
use OffreGen\Donnees\Portee;
use OffreGen\Http\Conteneur;
use OffreGen\Http\Flash;
use OffreGen\Http\PageIntrouvable;
use OffreGen\Http\Parametres;
use OffreGen\Offre\Saisie;
use OffreGen\Offre\Totaux;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * L'écran d'une offre (lignes rangées par catégorie, totaux), son en-tête (titre, client),
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
        return new Response($this->c->twig()->render('offre.html.twig', $this->avecFactures($requete, $this->donnees($requete, $parametres))));
    }

    /**
     * L'objet de l'offre. Le client se choisit dans sa fenêtre (ClientControleur::choixPourOffre).
     *
     * @param array<string, string> $parametres
     */
    public function entete(Request $requete, array $parametres): Response
    {
        $offre = $this->offre($requete, $parametres);
        return $this->formulaireEntete($offre, ['titre' => $offre['titre']], [], 200);
    }

    /** @param array<string, string> $parametres */
    public function modifierEntete(Request $requete, array $parametres): Response
    {
        $offre = $this->offre($requete, $parametres);
        $champs = ['titre' => $requete->request->getString('titre')];
        $titre = Saisie::texte($champs['titre'], 190);
        if ($titre === null) {
            return $this->formulaireEntete($offre, $champs, ['titre' => "Indiquez l'objet de l'offre (190 caractères au plus)."], 422);
        }
        $this->offres($requete)->modifierTitre($offre['id'], $titre, $this->c->horloge()->now());
        Flash::dire($requete, 'info', 'Offre enregistrée');
        return new RedirectResponse('/offres/' . $offre['id'], 303);
    }

    /** @param array<string, string> $parametres */
    public function document(Request $requete, array $parametres): Response
    {
        return new Response($this->c->twig()->render('document.html.twig', $this->donnees($requete, $parametres)));
    }

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
     * @param array<string, mixed> $d les données du document, avec « m » (Marque)
     */
    public static function rendrePdf(Conteneur $c, array $d): string
    {
        return $c->documentPdf()->rendre(self::htmlPdf($c, $d));
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
        $d['css'] = (string) file_get_contents($c->racine() . '/public/css/document.css') . "\n" . $m->css();
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
     * @return array{m: Marque, logo_src: string|null, offre: Offre, groupes: list<array{nom: string, lignes: list<array{l: Ligne, total: int}>, total: int}>, totaux: array{lignes: list<int>, ht: int, tva: int, arrondi: int, ttc: int}, tva: int, r: \OffreGen\Compte\Reglages, valable_jusqu_au: string, manque: int, doute: int}
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
     * @return array{m: Marque, logo_src: string|null, offre: Offre, groupes: list<array{nom: string, lignes: list<array{l: Ligne, total: int}>, total: int}>, totaux: array{lignes: list<int>, ht: int, tva: int, arrondi: int, ttc: int}, tva: int, r: \OffreGen\Compte\Reglages, valable_jusqu_au: string, manque: int, doute: int}
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
     * Sert aussi aux lignes d'une facture, qui ont la même forme.
     *
     * @template T of array{categorie: string|null}
     * @param list<T> $lignes
     * @param list<int> $totauxLignes total de chaque ligne, dans le même ordre
     * @return list<array{nom: string, lignes: list<array{l: T, total: int}>, total: int}>
     */
    public static function grouper(array $lignes, array $totauxLignes): array
    {
        $groupes = [];
        foreach ($lignes as $i => $l) {
            // Lire la catégorie à part : un « ?? » sur $l resserrerait son type et perdrait T.
            $categorie = $l['categorie'];
            $nom = $categorie ?? self::HORS_CATALOGUE;
            $groupes[$nom] ??= ['nom' => $nom, 'lignes' => [], 'total' => 0];
            $groupes[$nom]['lignes'][] = ['l' => $l, 'total' => $totauxLignes[$i] ?? 0];
            $groupes[$nom]['total'] += $totauxLignes[$i] ?? 0;
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
