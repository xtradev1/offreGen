<?php

declare(strict_types=1);

namespace OffreGen\Donnees;

use OffreGen\Catalogue\LectureOffre;

/**
 * Les imports de catalogue d'un artisan (chantier 2026-11-20_import-catalogue).
 * États : « lecture » (offres en cours de lecture), « relecture » (propositions prêtes), « integre », « annule ».
 *
 * @phpstan-import-type LigneImport from \OffreGen\Catalogue\Regroupement
 * @phpstan-type Import array{id: int, etat: string, offres: int, lignes: int, jetons_envoyes: int, jetons_recus: int, propositions: string|null, bilan: string|null, annulation: string|null, cree_le: string, integre_le: string|null}
 */
final class DepotImports
{
    public function __construct(private readonly Base $base, private readonly Portee $portee)
    {
    }

    public function creer(\DateTimeImmutable $maintenant): int
    {
        $this->base->ecrire('INSERT INTO import_catalogue (artisan_id, cree_le) VALUES (:a, :q)', ['a' => $this->portee->artisanId, 'q' => Dates::versBase($maintenant)]);
        return $this->base->dernierId();
    }

    /** Les imports créés depuis cette date, annulés compris : la lecture a coûté. */
    public function creesDepuis(\DateTimeImmutable $depuis): int
    {
        return (int) $this->base->valeur(
            'SELECT COUNT(*) FROM import_catalogue WHERE artisan_id = :a AND cree_le >= :d',
            ['a' => $this->portee->artisanId, 'd' => Dates::versBase($depuis)],
        );
    }

    public function aDejaIntegre(): bool
    {
        return (int) $this->base->valeur("SELECT COUNT(*) FROM import_catalogue WHERE artisan_id = :a AND etat IN ('integre', 'annule')", ['a' => $this->portee->artisanId]) > 0;
    }

    /** @return Import|null */
    public function parId(int $id): ?array
    {
        /** @var Import|null */
        return $this->base->un(
            'SELECT id, etat, offres, lignes, jetons_envoyes, jetons_recus, propositions, bilan, annulation, cree_le, integre_le
             FROM import_catalogue WHERE id = :id AND artisan_id = :a',
            ['id' => $id, 'a' => $this->portee->artisanId],
        );
    }

    /** Range les lignes lues d'une offre de plus ; seulement pendant la lecture. */
    public function ajouterLecture(int $id, LectureOffre $lecture): bool
    {
        return $this->base->transaction(function () use ($id, $lecture): bool {
            $import = $this->base->un("SELECT offres FROM import_catalogue WHERE id = :id AND artisan_id = :a AND etat = 'lecture' FOR UPDATE",
                ['id' => $id, 'a' => $this->portee->artisanId]);
            if ($import === null) {
                return false;
            }
            $offre = (int) $import['offres'];
            foreach ($lecture->lignes as $l) {
                $this->base->ecrire(
                    'INSERT INTO import_ligne (import_id, artisan_id, offre, designation, unite, unite_ecrite, prix_centimes) VALUES (:i, :a, :o, :d, :u, :e, :p)',
                    ['i' => $id, 'a' => $this->portee->artisanId, 'o' => $offre, 'd' => $l->designation, 'u' => $l->unite, 'e' => mb_substr($l->uniteEcrite, 0, 20), 'p' => $l->prixCentimes],
                );
            }
            $this->base->ecrire(
                'UPDATE import_catalogue SET offres = offres + 1, lignes = lignes + :n, jetons_envoyes = jetons_envoyes + :e, jetons_recus = jetons_recus + :r
                 WHERE id = :id AND artisan_id = :a',
                ['n' => count($lecture->lignes), 'e' => $lecture->jetonsEnvoyes ?? 0, 'r' => $lecture->jetonsRecus ?? 0, 'id' => $id, 'a' => $this->portee->artisanId],
            );
            return true;
        });
    }

    /** @return list<LigneImport> dans l'ordre de lecture */
    public function lignes(int $id): array
    {
        return array_map(static fn (array $l): array => [
            'designation' => (string) $l['designation'], 'unite' => $l['unite'] === null ? null : (string) $l['unite'],
            'unite_ecrite' => (string) $l['unite_ecrite'], 'prix_centimes' => (int) $l['prix_centimes'], 'offre' => (int) $l['offre'],
        ], $this->base->lire(
            'SELECT designation, unite, unite_ecrite, prix_centimes, offre FROM import_ligne WHERE import_id = :i AND artisan_id = :a ORDER BY offre, id',
            ['i' => $id, 'a' => $this->portee->artisanId],
        ));
    }

    /** Ajoute les jetons d'un appel qui ne lit pas une offre (la comparaison des libellés). */
    public function ajouterJetons(int $id, int $envoyes, int $recus): void
    {
        $this->base->ecrire('UPDATE import_catalogue SET jetons_envoyes = jetons_envoyes + :e, jetons_recus = jetons_recus + :r WHERE id = :id AND artisan_id = :a',
            ['e' => $envoyes, 'r' => $recus, 'id' => $id, 'a' => $this->portee->artisanId]);
    }

    public function enregistrerPropositions(int $id, string $propositions): bool
    {
        return $this->base->ecrire(
            "UPDATE import_catalogue SET propositions = :p, etat = 'relecture' WHERE id = :id AND artisan_id = :a AND etat = 'lecture'",
            ['p' => $propositions, 'id' => $id, 'a' => $this->portee->artisanId],
        ) === 1;
    }

    /** Fin de l'import : le bilan et de quoi l'annuler restent ; les lignes lues et les propositions s'effacent. */
    public function marquerIntegre(int $id, string $bilan, string $annulation, \DateTimeImmutable $maintenant): bool
    {
        $fait = $this->base->ecrire(
            "UPDATE import_catalogue SET etat = 'integre', bilan = :b, annulation = :n, propositions = NULL, integre_le = :q
             WHERE id = :id AND artisan_id = :a AND etat = 'relecture'",
            ['b' => $bilan, 'n' => $annulation, 'q' => Dates::versBase($maintenant), 'id' => $id, 'a' => $this->portee->artisanId],
        ) === 1;
        $this->base->ecrire('DELETE FROM import_ligne WHERE import_id = :i AND artisan_id = :a', ['i' => $id, 'a' => $this->portee->artisanId]);
        return $fait;
    }

    public function marquerAnnule(int $id): bool
    {
        return $this->base->ecrire(
            "UPDATE import_catalogue SET etat = 'annule', annulation = NULL WHERE id = :id AND artisan_id = :a AND etat = 'integre'",
            ['id' => $id, 'a' => $this->portee->artisanId],
        ) === 1;
    }

    /** Remise à zéro d'une démonstration : le prochain visiteur retrouve ses trois imports. */
    public function toutEffacer(): void
    {
        $this->base->ecrire('DELETE FROM import_ligne WHERE artisan_id = :a', ['a' => $this->portee->artisanId]);
        $this->base->ecrire('DELETE FROM import_catalogue WHERE artisan_id = :a', ['a' => $this->portee->artisanId]);
    }
}
