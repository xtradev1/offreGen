<?php

declare(strict_types=1);

namespace OffreGen\Http\Controleur;

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
        return new Response($this->c->twig()->render('offre.html.twig', $this->donnees($requete, $parametres)));
    }

    /** @param array<string, string> $parametres */
    public function entete(Request $requete, array $parametres): Response
    {
        $offre = $this->offre($requete, $parametres);
        $champs = ['titre' => $offre['titre'], 'client_nom' => $offre['client_nom'] ?? '', 'client_adresse' => $offre['client_adresse'] ?? ''];
        return $this->formulaireEntete($offre, $champs, [], 200);
    }

    /** @param array<string, string> $parametres */
    public function modifierEntete(Request $requete, array $parametres): Response
    {
        $offre = $this->offre($requete, $parametres);
        $r = $requete->request;
        $champs = ['titre' => $r->getString('titre'), 'client_nom' => $r->getString('client_nom'), 'client_adresse' => $r->getString('client_adresse')];
        $erreurs = [];
        $titre = Saisie::texte($champs['titre'], 190);
        if ($titre === null) {
            $erreurs['titre'] = "Indiquez l'objet de l'offre (190 caractères au plus).";
        }
        $nom = trim($champs['client_nom']) === '' ? '' : Saisie::texte($champs['client_nom'], 190);
        if ($nom === null) {
            $erreurs['client_nom'] = '190 caractères au plus.';
        }
        $adresse = Saisie::lignes($champs['client_adresse'], 4, 255);
        if ($adresse === null) {
            $erreurs['client_adresse'] = 'Quatre lignes et 255 caractères au plus.';
        }
        if ($titre === null || $nom === null || $adresse === null || $erreurs !== []) {
            return $this->formulaireEntete($offre, $champs, $erreurs, 422);
        }
        $this->offres($requete)->modifierEntete($offre['id'], $titre, $nom === '' ? null : $nom, $adresse === '' ? null : $adresse, $this->c->horloge()->now());
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
        $d['css'] = (string) file_get_contents($this->c->racine() . '/public/css/document.css');
        $pdf = $this->c->documentPdf()->rendre($this->c->twig()->render('document_pdf.html.twig', $d));
        $nom = 'Offre-' . $d['offre']['numero'] . '.pdf';
        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $nom . '"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * Tout ce que montrent l'écran de l'offre et son document.
     *
     * @param array<string, string> $parametres
     * @return array{offre: Offre, groupes: list<array{nom: string, lignes: list<array{l: Ligne, total: int}>, total: int}>, totaux: array{lignes: list<int>, ht: int, tva: int, arrondi: int, ttc: int}, tva: int, r: \OffreGen\Compte\Reglages, valable_jusqu_au: string, manque: int, doute: int}
     */
    private function donnees(Request $requete, array $parametres): array
    {
        $portee = new Portee($requete->attributes->getInt('_artisan'));
        $offre = $this->offre($requete, $parametres);
        $lignes = $this->c->offres($portee)->lignes($offre['id']);
        $r = $this->c->reglages($portee)->lire();
        $totaux = Totaux::calculer($lignes, $r->tvaPourDixMille, $r->arrondiCinqCentimes);
        $date = new \DateTimeImmutable($offre['modifiee_le'] ?? $offre['cree_le'], new \DateTimeZone('UTC'));
        $valable = $date->setTimezone(new \DateTimeZone('Europe/Zurich'))->modify('+' . $r->validiteJours . ' days')->format('d.m.Y');
        return [
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
     *
     * @param list<Ligne> $lignes
     * @param list<int> $totauxLignes total de chaque ligne, dans le même ordre
     * @return list<array{nom: string, lignes: list<array{l: Ligne, total: int}>, total: int}>
     */
    public static function grouper(array $lignes, array $totauxLignes): array
    {
        $groupes = [];
        foreach ($lignes as $i => $l) {
            $nom = $l['categorie'] ?? self::HORS_CATALOGUE;
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
