<?php

declare(strict_types=1);

namespace OffreGen\Donnees;

/**
 * Les clés des gestes reçus d'un artisan (chantier sans rechargement, lot 4) : un geste rejoué avec la même clé n'est
 * fait qu'une fois. Réserver la clé avant l'action, noter l'adresse où elle menait après ; libérer la clé si l'action
 * n'a rien fait (refus, erreur), pour qu'un nouvel essai reparte.
 */
final class DepotEnvois
{
    private const GARDE_JOURS = 30;

    public function __construct(private readonly Base $base, private readonly Portee $portee)
    {
    }

    /**
     * L'adresse où menait le geste déjà reçu sous cette clé ; '' s'il tourne encore ; null si la clé est neuve.
     */
    public function adresse(string $cle): ?string
    {
        $adresse = $this->base->valeur(
            'SELECT adresse FROM envoi WHERE artisan_id = :a AND cle = :c',
            ['a' => $this->portee->artisanId, 'c' => $cle],
        );
        return is_string($adresse) ? $adresse : null;
    }

    /** Prend la clé ; faux si un autre envoi l'a prise entre-temps. Les clés de plus de 30 jours partent. */
    public function reserver(string $cle, \DateTimeImmutable $maintenant): bool
    {
        $this->base->ecrire(
            'DELETE FROM envoi WHERE artisan_id = :a AND recu_le < :limite',
            ['a' => $this->portee->artisanId, 'limite' => Dates::versBase($maintenant->modify('-' . self::GARDE_JOURS . ' days'))],
        );
        return $this->base->ecrire(
            'INSERT IGNORE INTO envoi (artisan_id, cle, adresse, recu_le) VALUES (:a, :c, \'\', :r)',
            ['a' => $this->portee->artisanId, 'c' => $cle, 'r' => Dates::versBase($maintenant)],
        ) === 1;
    }

    public function noter(string $cle, string $adresse): void
    {
        $this->base->ecrire(
            'UPDATE envoi SET adresse = :ad WHERE artisan_id = :a AND cle = :c',
            ['ad' => mb_substr($adresse, 0, 500), 'a' => $this->portee->artisanId, 'c' => $cle],
        );
    }

    public function liberer(string $cle): void
    {
        $this->base->ecrire('DELETE FROM envoi WHERE artisan_id = :a AND cle = :c', ['a' => $this->portee->artisanId, 'c' => $cle]);
    }
}
