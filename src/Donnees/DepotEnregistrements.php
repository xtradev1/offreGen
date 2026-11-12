<?php

declare(strict_types=1);

namespace OffreGen\Donnees;

/**
 * Les enregistrements d'un artisan, vus depuis ses pages. Le traitement de fond passe par FileTraitement.
 *
 * @phpstan-type Enregistrement array{id: int, uuid: string, nom_origine: string, type_mime: string, taille: int, fichier: string|null, statut: string, tentatives: int, erreur: string|null, recu_le: string, pret_le: string|null, offre_id: int|null}
 */
final class DepotEnregistrements
{
    private const COLONNES = 'e.id, e.uuid, e.nom_origine, e.type_mime, e.taille, e.fichier, e.statut, e.tentatives, e.erreur, e.recu_le, e.pret_le, o.id AS offre_id';

    public function __construct(private readonly Base $base, private readonly Portee $portee)
    {
    }

    public function creer(string $uuid, string $nomOrigine, string $mime, int $taille, string $fichier, \DateTimeImmutable $recuLe): int
    {
        $this->base->ecrire(
            'INSERT INTO enregistrement (artisan_id, uuid, nom_origine, type_mime, taille, fichier, statut, recu_le)
             VALUES (:a, :u, :n, :m, :t, :f, :s, :r)',
            ['a' => $this->portee->artisanId, 'u' => $uuid, 'n' => mb_substr($nomOrigine, 0, 255), 'm' => $mime,
             't' => $taille, 'f' => $fichier, 's' => Statut::RECU, 'r' => Dates::versBase($recuLe)],
        );
        return $this->base->dernierId();
    }

    /** @return Enregistrement|null */
    public function parUuid(string $uuid): ?array
    {
        /** @var Enregistrement|null */
        return $this->base->un(
            'SELECT ' . self::COLONNES . ' FROM enregistrement e LEFT JOIN offre o ON o.enregistrement_id = e.id AND o.artisan_id = e.artisan_id
             WHERE e.uuid = :u AND e.artisan_id = :a',
            ['u' => $uuid, 'a' => $this->portee->artisanId],
        );
    }

    /** @return list<Enregistrement> les enregistrements pas encore prêts, du plus récent au plus ancien */
    public function enCours(): array
    {
        /** @var list<Enregistrement> */
        return $this->base->lire(
            'SELECT ' . self::COLONNES . ' FROM enregistrement e LEFT JOIN offre o ON o.enregistrement_id = e.id AND o.artisan_id = e.artisan_id
             WHERE e.artisan_id = :a AND e.statut <> :pret ORDER BY e.recu_le DESC, e.id DESC LIMIT 50',
            ['a' => $this->portee->artisanId, 'pret' => Statut::PRET],
        );
    }

    /** Imports reçus depuis $depuis : c'est ce qui coûte (transcription, modèle), donc ce que le plafond démo compte. */
    public function recusDepuis(\DateTimeImmutable $depuis): int
    {
        return (int) $this->base->valeur(
            'SELECT COUNT(*) FROM enregistrement WHERE artisan_id = :a AND recu_le >= :d',
            ['a' => $this->portee->artisanId, 'd' => Dates::versBase($depuis)],
        );
    }

    /** @return list<string> chemins relatifs des fichiers audio encore présents */
    public function fichiers(): array
    {
        /** @var list<string> */
        return array_column($this->base->lire(
            'SELECT fichier FROM enregistrement WHERE artisan_id = :a AND fichier IS NOT NULL',
            ['a' => $this->portee->artisanId],
        ), 'fichier');
    }

    /** Efface tous les enregistrements de l'artisan (réinitialisation d'un compte démo). */
    public function toutEffacer(): void
    {
        $this->base->ecrire('DELETE FROM enregistrement WHERE artisan_id = :a', ['a' => $this->portee->artisanId]);
    }
}
