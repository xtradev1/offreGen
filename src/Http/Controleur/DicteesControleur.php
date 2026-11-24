<?php

declare(strict_types=1);

namespace OffreGen\Http\Controleur;

use OffreGen\Audio\RapportDictees;
use OffreGen\Audio\Rejeu;
use OffreGen\Compte\Role;
use OffreGen\Donnees\DepotDictees;
use OffreGen\Donnees\PorteeEquipe;
use OffreGen\Http\Conteneur;
use OffreGen\Http\Flash;
use OffreGen\Http\PageIntrouvable;
use OffreGen\Systeme\Navigateur;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * La page des dictées de l'administrateur (sous-chantier 31, D1 à D7 de Xavier du 2026-11-24) : statistiques de la
 * période, liste par pages de 100, rapport d'une dictée sur une frise, export CSV de toute la période.
 *
 * @phpstan-import-type Ligne from RapportDictees
 */
final class DicteesControleur
{
    public const PAR_PAGE = 100;
    public const FILTRES = ['toutes' => 'Toutes', 'marquees' => 'Avec une étape marquée', 'accrocs' => 'Avec une reprise ou une erreur'];

    public function __construct(private readonly Conteneur $c)
    {
    }

    public function liste(Request $requete): Response
    {
        [$du, $au, $filtre] = $this->criteres($requete);
        [$lignes, $stats] = $this->lignes($du, $au, $filtre);
        $pages = max(1, (int) ceil(count($lignes) / self::PAR_PAGE));
        $page = min($pages, max(1, $requete->query->getInt('page', 1)));
        return $this->page('admin/dictees.html.twig', [
            'titre' => 'Dictées',
            'lignes' => array_map(self::vue(...), array_slice($lignes, ($page - 1) * self::PAR_PAGE, self::PAR_PAGE)),
            'nombre' => count($lignes),
            'stats' => $stats,
            'colonnes' => array_intersect_key(RapportDictees::LIBELLES, RapportDictees::COLONNES),
            'libelles' => RapportDictees::LIBELLES,
            'appels' => RapportDictees::APPELS,
            'filtres' => self::FILTRES,
            'criteres' => ['du' => $requete->query->getString('du'), 'au' => $requete->query->getString('au'), 'filtre' => $filtre],
            'page_n' => $page,
            'pages' => $pages,
        ]);
    }

    public function csv(Request $requete): Response
    {
        [$du, $au, $filtre] = $this->criteres($requete);
        [$lignes] = $this->lignes($du, $au, $filtre);
        $sortie = fopen('php://temp', 'r+') ?: throw new \RuntimeException('Mémoire temporaire indisponible.');
        $entetes = ['recue', 'offre', 'compte', 'statut', 'tentatives', 'audio_ms', 'taille_octets', 'format', 'navigateur', 'origine'];
        foreach (RapportDictees::ETAPES as $etape) {
            $entetes[] = $etape . '_ms';
            if (in_array($etape, RapportDictees::APPELS, true)) {
                $entetes[] = $etape . '_jetons_envoyes';
                $entetes[] = $etape . '_jetons_recus';
            }
        }
        array_push($entetes, 'hors_etapes_ms', 'total_ms', 'lentes', 'modele_ia', 'modele_transcription');
        fputcsv($sortie, $entetes, ';', '"', '');
        foreach ($lignes as $l) {
            $d = $l['dictee'];
            $ligne = [self::heureLocale($d['recu_le']), $d['offre_id'], $d['artisan_id'], $d['statut'], $l['essais'], $d['duree_ms'], $d['taille'],
                $d['type_mime'], Navigateur::lisible($d['navigateur']), $d['origine']];
            foreach (RapportDictees::ETAPES as $etape) {
                $ligne[] = $l['detail'][$etape] ?? null;
                if (in_array($etape, RapportDictees::APPELS, true)) {
                    $ligne[] = $l['jetons'][$etape][0] ?? null;
                    $ligne[] = $l['jetons'][$etape][1] ?? null;
                }
            }
            array_push($ligne, $l['hors'], $l['total'], implode(' ', $l['lentes']), $l['modele_ia'], $l['modele_transcription']);
            fputcsv($sortie, $ligne, ';', '"', '');
        }
        rewind($sortie);
        // BOM : Excel lit alors l'UTF-8 (« Pièces », « Écriture »).
        $reponse = new Response("\u{FEFF}" . stream_get_contents($sortie), 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="dictees-' . $this->c->horloge()->now()->setTimezone(new \DateTimeZone('Europe/Zurich'))->format('Y-m-d') . '.csv"',
            'Cache-Control' => 'no-store',
        ]);
        fclose($sortie);
        return $reponse;
    }

    /** @param array<string, string> $p */
    public function rapport(array $p): Response
    {
        $id = ctype_digit($p['id'] ?? '') ? (int) $p['id'] : 0;
        $depot = $this->depot();
        $dictee = $depot->dictee($id) ?? throw new PageIntrouvable();
        $etapes = $depot->etapesDe($id);
        $propres = array_values(array_filter($etapes, static fn (array $e): bool => $e['rejeu_id'] === null));
        [$ligne] = RapportDictees::lignes([$dictee], $propres);
        $rejeux = [];
        foreach ($depot->rejeux($id) as $rj) {
            $siennes = array_values(array_filter($etapes, static fn (array $e): bool => $e['rejeu_id'] === $rj['id']));
            [$l] = RapportDictees::lignes([$dictee], $siennes);
            $rejeux[] = $rj + ['frise' => RapportDictees::frise($siennes), 'colonnes' => $l['colonnes'], 'total' => $l['total']];
        }
        return $this->page('admin/dictee.html.twig', [
            'titre' => 'Dictée ' . ($dictee['offre_id'] === null ? 'sans offre' : 'de l\'offre ' . $dictee['offre_id']),
            'd' => $dictee,
            'ligne' => self::vue($ligne),
            'frise' => RapportDictees::frise($propres),
            'original' => $depot->original($id),
            'rejeux' => $rejeux,
            'audio' => $depot->audioPresent($id),
            'colonnes' => array_intersect_key(RapportDictees::LIBELLES, RapportDictees::COLONNES),
            'etapes' => array_map(static fn (array $e): array => $e + [
                'nom' => RapportDictees::NOMS_ETAPES[$e['etape']] ?? $e['etape'],
                'nom_processus' => RapportDictees::NOMS_PROCESSUS[$e['processus']] ?? $e['processus'],
            ], $propres),
        ]);
    }

    /**
     * D2 de Xavier (2026-11-24) : rejouer la dictée pour voir où se situe le problème. L'offre n'est pas touchée ; le
     * rejeu tourne après la réponse et le rapport le montre à côté de l'original. Un à la fois par dictée.
     *
     * @param array<string, string> $p
     */
    public function rejouer(Request $requete, array $p): Response
    {
        $id = ctype_digit($p['id'] ?? '') ? (int) $p['id'] : 0;
        $depot = $this->depot();
        if ($depot->dictee($id) === null) {
            throw new PageIntrouvable();
        }
        $maintenant = $this->c->horloge()->now();
        $refus = match (true) {
            !$depot->audioPresent($id) => 'L\'audio de cette dictée est supprimé : rejeu impossible.',
            $depot->rejeuEnCours($id, $maintenant->modify('-' . Rejeu::BLOCAGE_MINUTES . ' minutes')) => 'Un rejeu de cette dictée est déjà en cours.',
            default => null,
        };
        if ($refus !== null) {
            Flash::dire($requete, 'erreur', $refus);
            return new RedirectResponse('/admin/dictees/' . $id, 303);
        }
        $rejeuId = $depot->creerRejeu($id, $this->acteur(), $maintenant);
        $this->c->apresReponse()->ajouter(function () use ($rejeuId): void {
            $this->c->rejeu()->executer($rejeuId);
        });
        Flash::dire($requete, 'info', 'Rejeu lancé : rechargez la page dans une minute.');
        return new RedirectResponse('/admin/dictees/' . $id . '#rejeux', 303);
    }

    /**
     * Les lignes de la période, marquées et filtrées, et les statistiques de toute la période (le filtre n'en change
     * pas les médianes : « marquée » se juge contre toutes les dictées de la période).
     *
     * @return array{list<Ligne>, array{colonnes: array<string, array<string, mixed>>, par_jetons: array<string, array<string, mixed>>}}
     */
    private function lignes(?\DateTimeImmutable $du, ?\DateTimeImmutable $au, string $filtre): array
    {
        $depot = $this->depot();
        $lignes = RapportDictees::lignes($depot->dictees($du, $au), $depot->etapes($du, $au));
        $stats = RapportDictees::statistiques($lignes);
        $lignes = RapportDictees::marquer($lignes, $stats);
        $lignes = array_values(array_filter($lignes, static fn (array $l): bool => match ($filtre) {
            'marquees' => $l['lentes'] !== [],
            'accrocs' => $l['accroc'],
            default => true,
        }));
        return [$lignes, $stats];
    }

    /**
     * @param Ligne $l
     * @return array<string, mixed>
     */
    private static function vue(array $l): array
    {
        $total = max(1, $l['total'] ?? 1);
        $parts = [];
        $x = 0;
        // La barre d'une carte au téléphone : la part de chaque colonne dans le total, à la suite (SVG, 0 à 1000).
        foreach ($l['colonnes'] as $colonne => $ms) {
            if ($ms !== null && $ms > 0) {
                $largeur = (int) round($ms * 1000 / $total);
                $parts[] = ['colonne' => $colonne, 'x' => $x, 'largeur' => max(2, $largeur)];
                $x += $largeur;
            }
        }
        $pire = null;
        foreach ($l['lentes'] as $colonne) {
            if ($pire === null || ($l['colonnes'][$colonne] ?? 0) > ($l['colonnes'][$pire] ?? 0)) {
                $pire = $colonne;
            }
        }
        return $l + ['navigateur' => Navigateur::lisible($l['dictee']['navigateur']), 'parts' => $parts, 'pire' => $pire];
    }

    /** @return array{\DateTimeImmutable|null, \DateTimeImmutable|null, string} bornes UTC (au : exclue) et filtre */
    private function criteres(Request $requete): array
    {
        $zurich = new \DateTimeZone('Europe/Zurich');
        $jour = static function (string $texte) use ($zurich): ?\DateTimeImmutable {
            $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $texte, $zurich);
            return $d === false || $d->format('Y-m-d') !== $texte ? null : $d;
        };
        $du = $jour($requete->query->getString('du'));
        $au = $jour($requete->query->getString('au'))?->modify('+1 day');
        $filtre = array_key_exists($requete->query->getString('filtre'), self::FILTRES) ? $requete->query->getString('filtre') : 'toutes';
        $utc = new \DateTimeZone('UTC');
        return [$du?->setTimezone($utc), $au?->setTimezone($utc), $filtre];
    }

    private static function heureLocale(string $utc): string
    {
        return (new \DateTimeImmutable($utc, new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('Europe/Zurich'))->format('Y-m-d H:i:s');
    }

    private function depot(): DepotDictees
    {
        return new DepotDictees($this->c->base(), new PorteeEquipe($this->acteur(), $this->role() !== Role::Commercial));
    }

    /**
     * Même cadre que les pages d'AdminControleur : rôle, demandes d'appel en attente sur l'onglet, rien dans le cache.
     *
     * @param array<string, mixed> $vars
     */
    private function page(string $gabarit, array $vars): Response
    {
        $portee = new PorteeEquipe($this->acteur(), $this->role() !== Role::Commercial);
        $vars += ['role' => $this->role(), 'actif' => 'plus', 'appels_en_attente' => $this->c->depotAppels()->nombreARappeler($portee)];
        $reponse = new Response($this->c->twig()->render($gabarit, $vars));
        $reponse->headers->set('Cache-Control', 'no-store');
        return $reponse;
    }

    private function role(): Role
    {
        return $this->c->garde()->roleCourant() ?? throw new \LogicException("Route d'administration sans rôle.");
    }

    private function acteur(): int
    {
        return $this->c->garde()->artisanCourant() ?? throw new \LogicException("Route d'administration sans compte.");
    }
}
