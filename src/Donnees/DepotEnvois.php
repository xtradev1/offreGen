<?php

declare(strict_types=1);

namespace OffreGen\Donnees;

/**
 * Les clés des gestes reçus d'un artisan (chantier sans rechargement, lot 4) : un geste rejoué avec la même clé n'est
 * fait qu'une fois. Réserver la clé avant l'action, noter l'adresse où elle menait et ses messages après ; libérer la
 * clé si l'action n'a rien fait (refus, erreur), pour qu'un nouvel essai reparte.
 */
final class DepotEnvois
{
    private const GARDE_JOURS = 30;
    /**
     * Une réservation sans adresse plus vieille que cela est abandonnée (processus tué, erreur fatale) : libérée, le geste
     * repart. Bien au-delà de la plus longue action d'un artisan ; sans elle, la file du téléphone restait bloquée
     * (revue du 2026-11-23, B1).
     */
    private const ABANDON_SECONDES = 180;

    public function __construct(private readonly Base $base, private readonly Portee $portee)
    {
    }

    /**
     * Le geste déjà reçu sous cette clé : l'adresse où il menait ('' s'il tourne encore) et ses messages ; null si la clé
     * est neuve, ou si sa réservation était abandonnée (elle est alors libérée).
     *
     * @return array{adresse: string, messages: array<string, list<mixed>>}|null
     */
    public function deja(string $cle, \DateTimeImmutable $maintenant): ?array
    {
        $ligne = $this->base->un(
            'SELECT adresse, messages, recu_le FROM envoi WHERE artisan_id = :a AND cle = :c',
            ['a' => $this->portee->artisanId, 'c' => $cle],
        );
        if ($ligne === null) {
            return null;
        }
        $adresse = (string) $ligne['adresse'];
        if ($adresse === '' && Dates::depuisBase((string) $ligne['recu_le'])->getTimestamp() < $maintenant->getTimestamp() - self::ABANDON_SECONDES) {
            $this->liberer($cle);
            return null;
        }
        $messages = is_string($ligne['messages']) ? json_decode($ligne['messages'], true) : null;
        /** @var array<string, list<mixed>> $messages */
        $messages = is_array($messages) ? $messages : [];
        return ['adresse' => $adresse, 'messages' => $messages];
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

    /** @param array<string, list<mixed>> $messages */
    public function noter(string $cle, string $adresse, array $messages): void
    {
        $this->base->ecrire(
            'UPDATE envoi SET adresse = :ad, messages = :m WHERE artisan_id = :a AND cle = :c',
            ['ad' => mb_substr($adresse, 0, 500), 'm' => $messages === [] ? null : json_encode($messages, JSON_UNESCAPED_UNICODE),
             'a' => $this->portee->artisanId, 'c' => $cle],
        );
    }

    public function liberer(string $cle): void
    {
        $this->base->ecrire('DELETE FROM envoi WHERE artisan_id = :a AND cle = :c', ['a' => $this->portee->artisanId, 'c' => $cle]);
    }
}
