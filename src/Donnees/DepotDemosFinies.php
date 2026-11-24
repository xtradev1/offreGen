<?php

declare(strict_types=1);

namespace OffreGen\Donnees;

/**
 * Les démos de l'équipe finies depuis longtemps, et leur effacement complet (Compte\SuppressionDemos). Hors portée
 * d'artisan par nature : ne sert qu'à la tâche de nuit bin/supprimer-demos.
 */
final class DepotDemosFinies
{
    /** Les tables d'un compte, dans l'ordre que demandent leurs clés étrangères. Le journal de l'équipe reste. */
    private const EFFACER = [
        'DELETE FROM facture_pdf WHERE artisan_id = :a',
        'DELETE FROM facture_ligne WHERE artisan_id = :a',
        'UPDATE facture SET refaite_depuis = NULL WHERE artisan_id = :a',
        'DELETE FROM facture WHERE artisan_id = :a',
        'DELETE FROM ligne WHERE artisan_id = :a',
        'DELETE FROM offre WHERE artisan_id = :a',
        'DELETE FROM client WHERE artisan_id = :a',
        'DELETE FROM enregistrement WHERE artisan_id = :a',
        'DELETE FROM import_ligne WHERE artisan_id = :a',
        'DELETE FROM import_catalogue WHERE artisan_id = :a',
        'DELETE FROM produit WHERE artisan_id = :a',
        'DELETE FROM demande_appel WHERE artisan_id = :a',
        'DELETE FROM jeton_acces WHERE artisan_id = :a',
    ];

    public function __construct(private readonly Base $base)
    {
    }

    /**
     * Les démos de l'équipe finies avant cette limite. Jamais un essai, une démonstration partagée (sans date de fin)
     * ou un membre de l'équipe.
     *
     * @return list<array{id: int, logo_fichier: string|null}>
     */
    public function finiesAvant(\DateTimeImmutable $limite): array
    {
        /** @var list<array{id: int, logo_fichier: string|null}> */
        return $this->base->lire(
            'SELECT id, logo_fichier FROM artisan WHERE demo = 1 AND demo_fin IS NOT NULL AND demo_fin <= :l AND administrateur = 0 AND role IS NULL ORDER BY id',
            ['l' => Dates::versBase($limite)],
        );
    }

    /**
     * Efface le compte et tout ce qui lui appartient, en une transaction. Relu dans la transaction : une démo passée à
     * l'essai entre-temps n'est pas touchée.
     *
     * @throws \RuntimeException si le compte n'est plus une démo finie avant la limite (rien n'est effacé)
     */
    public function effacer(int $id, \DateTimeImmutable $limite): void
    {
        $this->base->transaction(function () use ($id, $limite): void {
            foreach (self::EFFACER as $sql) {
                $this->base->ecrire($sql, ['a' => $id]);
            }
            $effaces = $this->base->ecrire(
                'DELETE FROM artisan WHERE id = :a AND demo = 1 AND demo_fin <= :l AND administrateur = 0 AND role IS NULL',
                ['a' => $id, 'l' => Dates::versBase($limite)],
            );
            if ($effaces !== 1) {
                throw new \RuntimeException("la démo $id a changé pendant la suppression, rien n'est effacé");
            }
        });
    }
}
