<?php

declare(strict_types=1);

namespace OffreGen\Donnees;

/**
 * Les demandes d'appel (« Demander un appel », K1 et S1 de la maquette v4 de l'administration ; chantier onboarding,
 * décision E1 du 2026-11-23). Une demande en attente au plus par compte : une nouvelle demande la remplace. Côté
 * équipe, ces lectures traversent les comptes et suivent la portée du membre, comme DepotEquipe.
 *
 * @phpstan-type Demande array{id: int, artisan_id: int, telephone: string, moment: string, question: string|null, cree_le: string}
 * @phpstan-type ARappeler array{id: int, artisan_id: int, telephone: string, moment: string, question: string|null, cree_le: string, entreprise: string, demo: int, demo_fin: string|null, offres_max: int|null, dictees: int}
 */
final class DepotAppels
{
    /** La valeur gardée en base, et la façon de la dire après « Téléphone : 079…, ». */
    public const MOMENTS = ['matin' => 'le matin', 'midi' => 'à midi', 'soir' => 'en fin de journée'];

    /** Les demandes que voit un membre : ses clients pour un commercial, tous les comptes pour les autres rôles. */
    private const PORTEE = 'a.administrateur = 0 AND a.role IS NULL AND (:tout = 1 OR a.cree_par = :acteur)';

    public function __construct(private readonly Base $base)
    {
    }

    /** @return Demande|null la demande du compte qui attend encore un appel */
    public function enAttente(int $artisanId): ?array
    {
        /** @var Demande|null */
        return $this->base->un(
            'SELECT id, artisan_id, telephone, moment, question, cree_le FROM demande_appel WHERE artisan_id = :a AND traite_le IS NULL ORDER BY id DESC LIMIT 1',
            ['a' => $artisanId],
        );
    }

    /** Dépose la demande du compte, ou remplace celle qui attend encore. */
    public function deposer(int $artisanId, string $telephone, string $moment, ?string $question, \DateTimeImmutable $quand): void
    {
        $this->base->transaction(function () use ($artisanId, $telephone, $moment, $question, $quand): void {
            $vals = ['t' => $telephone, 'm' => $moment, 'q' => $question, 'c' => Dates::versBase($quand)];
            $attente = $this->enAttente($artisanId);
            if ($attente !== null) {
                $this->base->ecrire('UPDATE demande_appel SET telephone = :t, moment = :m, question = :q, cree_le = :c WHERE id = :id', $vals + ['id' => $attente['id']]);
                return;
            }
            $this->base->ecrire('INSERT INTO demande_appel (artisan_id, telephone, moment, question, cree_le) VALUES (:a, :t, :m, :q, :c)', $vals + ['a' => $artisanId]);
        });
    }

    /**
     * « À rappeler » (S1) : les demandes en attente dans la portée du membre, les plus anciennes d'abord.
     *
     * @return list<ARappeler>
     */
    public function aRappeler(PorteeEquipe $p): array
    {
        /** @var list<ARappeler> */
        return $this->base->lire(
            'SELECT d.id, d.artisan_id, d.telephone, d.moment, d.question, d.cree_le, a.entreprise, a.demo, a.demo_fin, a.offres_max,
                    (SELECT COUNT(*) FROM enregistrement e WHERE e.artisan_id = a.id) AS dictees
             FROM demande_appel d JOIN artisan a ON a.id = d.artisan_id
             WHERE d.traite_le IS NULL AND ' . self::PORTEE . ' ORDER BY d.cree_le, d.id',
            self::portee($p),
        );
    }

    /** Le nombre de demandes en attente, pour l'onglet de la barre du bas. */
    public function nombreARappeler(PorteeEquipe $p): int
    {
        return (int) $this->base->valeur(
            'SELECT COUNT(*) FROM demande_appel d JOIN artisan a ON a.id = d.artisan_id WHERE d.traite_le IS NULL AND ' . self::PORTEE,
            self::portee($p),
        );
    }

    /**
     * « C'est fait » : la demande sort de la liste. Hors de la portée du membre, rien n'est écrit.
     *
     * @return int|null le compte de la demande ; null si rien n'a été écrit
     */
    public function marquerFait(PorteeEquipe $p, int $id, \DateTimeImmutable $quand): ?int
    {
        $artisan = $this->base->valeur(
            'SELECT d.artisan_id FROM demande_appel d JOIN artisan a ON a.id = d.artisan_id WHERE d.id = :id AND d.traite_le IS NULL AND ' . self::PORTEE,
            self::portee($p) + ['id' => $id],
        );
        if ($artisan === false || $artisan === null) {
            return null;
        }
        $ecrit = $this->base->ecrire(
            'UPDATE demande_appel SET traite_le = :q, traite_par = :m WHERE id = :id AND traite_le IS NULL',
            ['q' => Dates::versBase($quand), 'm' => $p->acteurId, 'id' => $id],
        );
        return $ecrit === 1 ? (int) $artisan : null;
    }

    /** @return array{tout: int, acteur: int} */
    private static function portee(PorteeEquipe $p): array
    {
        return ['tout' => $p->toutVoir ? 1 : 0, 'acteur' => $p->acteurId];
    }
}
