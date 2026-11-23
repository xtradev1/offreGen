<?php

declare(strict_types=1);

namespace OffreGen\Http\Controleur;

use OffreGen\Catalogue\ComparateurFactice;
use OffreGen\Catalogue\Integration;
use OffreGen\Catalogue\Regroupement;
use OffreGen\Compte\Demos;
use OffreGen\Donnees\DepotImports;
use OffreGen\Donnees\Portee;
use OffreGen\Http\Conteneur;
use OffreGen\Http\Flash;
use OffreGen\Http\PageIntrouvable;
use OffreGen\Http\Parametres;
use OffreGen\Offre\Saisie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Import du catalogue depuis d'anciennes offres en PDF (chantier 2026-11-20_import-catalogue, maquette validée le
 * 2026-11-20). Le navigateur sort le texte de chaque PDF (public/js/texte-pdf.js) et l'envoie, une offre à la fois :
 * le fichier ne quitte jamais le téléphone ou le PC. Le serveur lit chaque offre par le modèle, regroupe les lignes,
 * montre ce qu'il propose ; rien n'entre au catalogue avant « Ajouter ». L'import se défait depuis son bilan.
 *
 * Trois imports par mois et cinq offres par import (Xavier, 2026-11-20 : « 3 imports par mois, il n'y a pas besoin de
 * plus ») : au plus quinze lectures par mois, environ CHF 0.002 l'offre d'une page chez Infomaniak (banc du 2026-11-20).
 *
 * @phpstan-import-type Import from DepotImports
 * @phpstan-import-type Proposition from Regroupement
 */
final class ImportCatalogueControleur
{
    public const PAR_MOIS = 3;
    public const OFFRES_MAX = 5;
    /** Le texte JSON d'une offre envoyé par le navigateur : 80 000 caractères au plus, accents et échappements compris. */
    private const CORPS_MAX = 400_000;

    public function __construct(private readonly Conteneur $c)
    {
    }

    public function page(Request $requete): Response
    {
        return new Response($this->c->twig()->render('import_catalogue.html.twig', [
            'restants' => $this->restants($requete),
            'par_mois' => self::PAR_MOIS,
            'offres_max' => self::OFFRES_MAX,
            'prochain' => $this->prochainMois(),
            'pdfjs' => OffreControleur::PDFJS,
        ]));
    }

    public function creer(Request $requete): JsonResponse
    {
        if ($this->demoTerminee($requete)) {
            return new JsonResponse(['erreur' => ImportControleur::DEMO_TERMINEE], 410);
        }
        if ($this->restants($requete) <= 0) {
            return new JsonResponse(['erreur' => sprintf("Vous avez fait vos %d imports de ce mois. Le prochain est possible dès le %s.", self::PAR_MOIS, $this->prochainMois())], 429);
        }
        return new JsonResponse(['id' => $this->imports($requete)->creer($this->c->horloge()->now())], 201);
    }

    /** @param array<string, string> $parametres */
    public function offre(Request $requete, array $parametres): JsonResponse
    {
        if ($this->demoTerminee($requete)) {
            return new JsonResponse(['erreur' => ImportControleur::DEMO_TERMINEE], 410);
        }
        $depot = $this->imports($requete);
        $import = $depot->parId(Parametres::id($parametres, 'id')) ?? throw new PageIntrouvable();
        if ($import['etat'] !== 'lecture' || $import['offres'] >= self::OFFRES_MAX) {
            return new JsonResponse(['erreur' => sprintf('%d offres au plus par import.', self::OFFRES_MAX)], 409);
        }
        $brut = $requete->request->getString('lignes');
        $texte = strlen($brut) > self::CORPS_MAX ? null : json_decode($brut, true);
        if (!is_array($texte) || !array_is_list($texte) || array_filter($texte, static fn (mixed $l): bool => !is_string($l)) !== []) {
            return new JsonResponse(['erreur' => 'Le texte de cette offre est illisible.'], 422);
        }
        /** @var list<string> $texte */
        // Lecture pendant la requête : 25 s au plus mesurées chez Infomaniak pour un devis de huit pages, relance comprise.
        set_time_limit(300);
        try {
            $lecture = $this->c->lecteur()->lire($texte);
        } catch (\InvalidArgumentException) {
            return new JsonResponse(['erreur' => 'Offre trop longue pour être lue.'], 422);
        } catch (\Throwable $e) {
            $this->c->journal()->warning('offreGen, import du catalogue : lecture d\'une offre en échec (' . $e::class . ').');
            return new JsonResponse(['erreur' => 'La lecture a échoué. Réessayez dans un moment.'], 502);
        }
        if (!$depot->ajouterLecture($import['id'], $lecture)) {
            return new JsonResponse(['erreur' => sprintf('%d offres au plus par import.', self::OFFRES_MAX)], 409);
        }
        return new JsonResponse(['lignes' => count($lecture->lignes)]);
    }

    /** @param array<string, string> $parametres */
    public function regrouper(Request $requete, array $parametres): JsonResponse
    {
        $portee = $this->portee($requete);
        $depot = $this->c->imports($portee);
        $import = $depot->parId(Parametres::id($parametres, 'id')) ?? throw new PageIntrouvable();
        $url = '/import-catalogue/' . $import['id'];
        if ($import['etat'] !== 'lecture') {
            // Envoyé deux fois : les propositions sont déjà prêtes.
            return new JsonResponse(['url' => $url]);
        }
        $lignes = $depot->lignes($import['id']);
        $catalogue = $this->c->produits($portee)->tous();
        $comparateur = $this->c->comparateur();
        try {
            $propositions = (new Regroupement($comparateur))->propositions($lignes, $catalogue);
        } catch (\Throwable $e) {
            // Sans le modèle, les libellés presque identiques se réunissent quand même ; l'artisan voit plus de lignes.
            $this->c->journal()->warning('offreGen, import du catalogue : comparaison des libellés en échec (' . $e::class . '), regroupement par le code seul.');
            $propositions = (new Regroupement(new ComparateurFactice()))->propositions($lignes, $catalogue);
        }
        [$envoyes, $recus] = $comparateur->jetonsConsommes();
        $depot->ajouterJetons($import['id'], $envoyes, $recus);
        $depot->enregistrerPropositions($import['id'], json_encode($propositions, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        return new JsonResponse(['url' => $url]);
    }

    /** @param array<string, string> $parametres */
    public function voir(Request $requete, array $parametres): Response
    {
        $import = $this->imports($requete)->parId(Parametres::id($parametres, 'id')) ?? throw new PageIntrouvable();
        return match ($import['etat']) {
            'lecture' => new RedirectResponse('/import-catalogue', 303),
            'relecture' => $this->relecture($import, [], [], 200),
            default => $this->bilan($import),
        };
    }

    /** @param array<string, string> $parametres */
    public function integrer(Request $requete, array $parametres): Response
    {
        $portee = $this->portee($requete);
        $depot = $this->c->imports($portee);
        $import = $depot->parId(Parametres::id($parametres, 'id')) ?? throw new PageIntrouvable();
        $retour = new RedirectResponse('/import-catalogue/' . $import['id'], 303);
        if ($import['etat'] !== 'relecture') {
            return $retour;
        }
        $propositions = self::propositions($import);
        $reponses = self::reponses($requete->request->all('p'));
        $resultat = Integration::actions($propositions, $reponses);
        if ($resultat['erreurs'] !== []) {
            return $this->relecture($import, $reponses, $resultat['erreurs'], 422);
        }
        $produits = $this->c->produits($portee);
        $maintenant = $this->c->horloge()->now();
        try {
            $this->c->base()->transaction(function () use ($resultat, $produits, $depot, $import, $maintenant): void {
                $annulation = ['crees' => [], 'avant' => []];
                foreach ($resultat['actions'] as $action) {
                    if ($action[0] === 'creer') {
                        $annulation['crees'][] = $produits->creer($action[1]);
                        continue;
                    }
                    $avant = $produits->parId($action[1]);
                    if ($avant === null) {
                        continue;
                    }
                    $annulation['avant'][$avant['id']] ??= ['prix' => $avant['prix_centimes'], 'exemple' => $avant['prix_exemple_centimes'], 'synonymes' => $avant['synonymes']];
                    if ($action[0] === 'prix') {
                        $produits->fixerPrix($avant['id'], $action[2]);
                    } else {
                        $synonymes = Integration::synonymes($avant['synonymes'], $action[2]);
                        $produits->changerSynonymes($avant['id'], $synonymes === '' ? null : $synonymes);
                    }
                }
                if (!$depot->marquerIntegre($import['id'], json_encode($resultat['bilan'], JSON_THROW_ON_ERROR), json_encode($annulation, JSON_THROW_ON_ERROR), $maintenant)) {
                    throw new ImportDejaIntegre();
                }
            });
        } catch (ImportDejaIntegre) {
            // Deux envois du même formulaire : le premier a tout écrit, le second ne touche à rien.
        }
        return $retour;
    }

    /** @param array<string, string> $parametres */
    public function annuler(Request $requete, array $parametres): Response
    {
        $portee = $this->portee($requete);
        $depot = $this->c->imports($portee);
        $import = $depot->parId(Parametres::id($parametres, 'id')) ?? throw new PageIntrouvable();
        if ($import['etat'] !== 'integre') {
            return new RedirectResponse('/import-catalogue/' . $import['id'], 303);
        }
        $annulation = json_decode((string) $import['annulation'], true);
        $annulation = is_array($annulation) ? $annulation : [];
        $produits = $this->c->produits($portee);
        $this->c->base()->transaction(function () use ($annulation, $produits, $depot, $import): void {
            foreach (is_array($annulation['crees'] ?? null) ? $annulation['crees'] : [] as $id) {
                if (is_int($id)) {
                    $produits->supprimer($id);
                }
            }
            foreach (is_array($annulation['avant'] ?? null) ? $annulation['avant'] : [] as $id => $a) {
                if (is_array($a) && is_int($a['prix'] ?? null)) {
                    $produits->restaurer((int) $id, $a['prix'], is_int($a['exemple'] ?? null) ? $a['exemple'] : null, is_string($a['synonymes'] ?? null) ? $a['synonymes'] : null);
                }
            }
            $depot->marquerAnnule($import['id']);
        });
        Flash::dire($requete, 'info', 'Import annulé : votre catalogue est revenu comme avant.');
        return new RedirectResponse('/catalogue', 303);
    }

    /**
     * @param Import $import
     * @param array<int, array<string, string>> $reponses
     * @param array<int, string> $erreurs
     */
    private function relecture(array $import, array $reponses, array $erreurs, int $code): Response
    {
        $propositions = self::propositions($import);
        $parType = ['choix' => [], 'complete' => [], 'nouveau' => [], 'identique' => []];
        foreach ($propositions as $i => $p) {
            $parType[$p['type']][$i] = $p;
        }
        $aChoisir = 0;
        foreach ($parType['choix'] as $i => $p) {
            $aChoisir += isset($reponses[$i]['choix']) ? 0 : 1;
        }
        return new Response($this->c->twig()->render('import_relecture.html.twig', [
            'import' => $import,
            'par_type' => $parType,
            'reponses' => $reponses,
            'erreurs' => $erreurs,
            'premiere' => $reponses === [],
            'a_choisir' => $aChoisir,
            'unites' => Saisie::UNITES,
        ]), $code);
    }

    /** @param Import $import */
    private function bilan(array $import): Response
    {
        $bilan = json_decode((string) $import['bilan'], true);
        $bilan = is_array($bilan) ? $bilan : [];
        $nombre = static fn (string $cle): int => is_int($bilan[$cle] ?? null) ? $bilan[$cle] : 0;
        return new Response($this->c->twig()->render('import_bilan.html.twig', [
            'import' => $import,
            'nouveaux' => $nombre('nouveaux'),
            'completes' => $nombre('completes'),
            'changes' => $nombre('changes'),
            'cote' => $nombre('cote'),
            'total' => $nombre('nouveaux') + $nombre('completes') + $nombre('changes'),
        ]));
    }

    /**
     * @param Import $import
     * @return list<Proposition>
     */
    private static function propositions(array $import): array
    {
        $propositions = json_decode((string) $import['propositions'], true);
        /** @var list<Proposition> écrites par regrouper(), relues telles quelles */
        return is_array($propositions) && array_is_list($propositions) ? $propositions : [];
    }

    /**
     * Les réponses du formulaire, réduites à des textes courts sous des rangs entiers.
     *
     * @param array<mixed> $brutes
     * @return array<int, array<string, string>>
     */
    private static function reponses(array $brutes): array
    {
        $reponses = [];
        foreach ($brutes as $i => $champs) {
            if (!is_int($i) || !is_array($champs)) {
                continue;
            }
            foreach (['garder', 'designation', 'unite', 'prix', 'choix'] as $cle) {
                if (is_string($champs[$cle] ?? null)) {
                    $reponses[$i][$cle] = mb_substr($champs[$cle], 0, 300);
                }
            }
        }
        return $reponses;
    }

    private function restants(Request $requete): int
    {
        return max(0, self::PAR_MOIS - $this->imports($requete)->creesDepuis($this->debutDuMois()));
    }

    /** Le premier du mois à minuit, à Zurich, exprimé en UTC. */
    private function debutDuMois(): \DateTimeImmutable
    {
        return $this->c->horloge()->now()->setTimezone(new \DateTimeZone('Europe/Zurich'))
            ->modify('first day of this month')->setTime(0, 0)->setTimezone(new \DateTimeZone('UTC'));
    }

    private function prochainMois(): string
    {
        $suivant = $this->c->horloge()->now()->setTimezone(new \DateTimeZone('Europe/Zurich'))->modify('first day of next month');
        $mois = (new \IntlDateFormatter('fr_CH', \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, 'Europe/Zurich', null, 'MMMM'))->format($suivant);
        return "1er\u{00A0}" . $mois;
    }

    /** Une démo finie ne lit plus d'offres par l'IA (revue du 2026-11-22, chantier onboarding). */
    private function demoTerminee(Request $requete): bool
    {
        $a = $this->c->artisans()->parId($requete->attributes->getInt('_artisan'));
        return $a !== null && $a['demo'] === 1 && Demos::terminee($a['demo_fin'], $this->c->horloge()->now());
    }

    private function portee(Request $requete): Portee
    {
        return new Portee($requete->attributes->getInt('_artisan'));
    }

    private function imports(Request $requete): DepotImports
    {
        return $this->c->imports($this->portee($requete));
    }
}
