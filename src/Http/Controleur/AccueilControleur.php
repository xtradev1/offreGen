<?php

declare(strict_types=1);

namespace OffreGen\Http\Controleur;

use OffreGen\Donnees\DepotOffres;
use OffreGen\Donnees\Portee;
use OffreGen\Donnees\Statut;
use OffreGen\Http\Conteneur;
use OffreGen\Offre\Totaux;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * La liste des offres (sous-chantier 21, réponses de Xavier du 2026-11-12) : ce qui se prépare et les
 * échecs en haut, les offres triées, les archives repliées en bas, le compte des offres du mois.
 *
 * @phpstan-import-type Offre from DepotOffres
 */
final class AccueilControleur
{
    /** Tris proposés : clé de l'adresse (?tri=) et libellé. « recent » est l'ordre par défaut. */
    public const TRIS = ['recent' => 'Récentes', 'client' => 'Client', 'montant' => 'Montant', 'relire' => 'À relire d\'abord'];

    public function __construct(private readonly Conteneur $c)
    {
    }

    public function liste(Request $requete): Response
    {
        $portee = new Portee($requete->attributes->getInt('_artisan'));
        // Premier lancement : l'assistant des réglages d'abord (choix de Xavier du 2026-11-12).
        if (!$this->c->reglages($portee)->assistantFait()) {
            return new RedirectResponse('/bienvenue/entreprise', 303);
        }
        $artisan = $this->c->artisans()->parId($portee->artisanId);
        $depot = $this->c->offres($portee);
        $offres = $depot->liste();
        $archives = $depot->archives();
        $lignes = $depot->lignesDe(array_merge(array_column($offres, 'id'), array_column($archives, 'id')));
        $r = $this->c->reglages($portee)->lire();
        $totaux = [];
        $aRelire = [];
        foreach ([...$offres, ...$archives] as $o) {
            $lesLignes = $lignes[$o['id']] ?? [];
            $totaux[$o['id']] = Totaux::calculer($lesLignes, $r->tvaPourDixMille, $r->arrondiCinqCentimes)['ttc'];
            $aRelire[$o['id']] = count(array_filter($lesLignes, static fn (array $l): bool => $l['a_verifier'] === 1));
        }
        $tri = $requete->query->getString('tri');
        $tri = array_key_exists($tri, self::TRIS) ? $tri : 'recent';

        $enCours = $this->c->enregistrements($portee)->enCours();
        return new Response($this->c->twig()->render('accueil.html.twig', [
            'entreprise' => $artisan['entreprise'] ?? '',
            'demo' => ($artisan['demo'] ?? 0) === 1,
            'en_preparation' => array_values(array_filter($enCours, static fn (array $e): bool => $e['statut'] !== Statut::ECHEC)),
            'echecs' => array_values(array_filter($enCours, static fn (array $e): bool => $e['statut'] === Statut::ECHEC)),
            'offres' => self::trier($offres, $tri, $totaux, $aRelire),
            'archives' => $archives,
            'totaux' => $totaux,
            'a_relire' => $aRelire,
            'tri' => $tri,
            'tris' => self::TRIS,
            'du_mois' => $depot->creeesDepuis($this->debutDuMois()),
        ]));
    }

    /**
     * @param list<Offre> $offres dans l'ordre par défaut (la dernière travaillée d'abord)
     * @param array<int, int> $totaux
     * @param array<int, int> $aRelire
     * @return list<Offre>
     */
    private static function trier(array $offres, string $tri, array $totaux, array $aRelire): array
    {
        // usort est stable : à égalité, l'ordre par défaut reste.
        $alphabet = new \Collator('fr_CH');
        $comparer = match ($tri) {
            'client' => static function (array $a, array $b) use ($alphabet): int {
                // De A à Z, sans tenir compte des accents ; les offres sans client à la fin.
                $vide = ($a['client_nom'] === null || $a['client_nom'] === '') <=> ($b['client_nom'] === null || $b['client_nom'] === '');
                if ($vide !== 0) {
                    return $vide;
                }
                $ordre = $alphabet->compare((string) $a['client_nom'], (string) $b['client_nom']);
                return $ordre === false ? strcmp((string) $a['client_nom'], (string) $b['client_nom']) : $ordre;
            },
            'montant' => static fn (array $a, array $b): int => ($totaux[$b['id']] ?? 0) <=> ($totaux[$a['id']] ?? 0),
            'relire' => static fn (array $a, array $b): int => (($aRelire[$b['id']] ?? 0) > 0) <=> (($aRelire[$a['id']] ?? 0) > 0),
            default => null,
        };
        if ($comparer !== null) {
            usort($offres, $comparer);
        }
        return $offres;
    }

    /** Le premier du mois à minuit, à Zurich, exprimé en UTC. */
    private function debutDuMois(): \DateTimeImmutable
    {
        return $this->c->horloge()->now()
            ->setTimezone(new \DateTimeZone('Europe/Zurich'))
            ->modify('first day of this month')
            ->setTime(0, 0)
            ->setTimezone(new \DateTimeZone('UTC'));
    }
}
