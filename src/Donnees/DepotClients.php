<?php

declare(strict_types=1);

namespace OffreGen\Donnees;

use OffreGen\Client\Rapprochement;

/**
 * Les clients d'un artisan. Une fiche naît quand l'artisan la crée ou confirme le nom dicté (règle de Xavier
 * du 2026-11-12) ; elle ne se supprime que si aucune offre visible ne la désigne.
 *
 * @phpstan-type Client array{id: int, nom: string, adresse: string|null, telephone: string|null, courriel: string|null}
 * @phpstan-type ClientListe array{id: int, nom: string, adresse: string|null, telephone: string|null, courriel: string|null, nb_offres: int, derniere: string|null}
 * @phpstan-type OffreDuClient array{id: int, numero: int, titre: string, cree_le: string, archivee_le: string|null}
 */
final class DepotClients
{
    private const COLONNES = 'c.id, c.nom, c.adresse, c.telephone, c.courriel';

    public function __construct(private readonly Base $base, private readonly Portee $portee)
    {
    }

    /** @return list<ClientListe> par ordre alphabétique, civilités ignorées ; le compte exclut les offres supprimées */
    public function tous(): array
    {
        /** @var list<ClientListe> $clients */
        $clients = $this->base->lire(
            'SELECT ' . self::COLONNES . ', COUNT(o.id) AS nb_offres, MAX(o.cree_le) AS derniere
             FROM client c LEFT JOIN offre o ON o.client_id = c.id AND o.artisan_id = c.artisan_id AND o.supprimee_le IS NULL
             WHERE c.artisan_id = :a GROUP BY c.id, c.nom, c.adresse, c.telephone, c.courriel',
            ['a' => $this->portee->artisanId],
        );
        $alphabet = new \Collator('fr_CH');
        $alphabet->setStrength(\Collator::PRIMARY);
        usort($clients, static function (array $x, array $y) use ($alphabet): int {
            $ordre = $alphabet->compare(Rapprochement::cleDeTri($x['nom']), Rapprochement::cleDeTri($y['nom']));
            return $ordre === false || $ordre === 0 ? $x['id'] <=> $y['id'] : $ordre;
        });
        return $clients;
    }

    /** @return list<array{id: int, nom: string}> */
    public function pourRapprochement(): array
    {
        /** @var list<array{id: int, nom: string}> */
        return $this->base->lire('SELECT id, nom FROM client WHERE artisan_id = :a', ['a' => $this->portee->artisanId]);
    }

    /** @return Client|null */
    public function parId(int $id): ?array
    {
        /** @var Client|null */
        return $this->base->un('SELECT ' . self::COLONNES . ' FROM client c WHERE c.id = :id AND c.artisan_id = :a', ['id' => $id, 'a' => $this->portee->artisanId]);
    }

    /** @return list<OffreDuClient> ses offres visibles, archivées comprises, de la plus récente à la plus ancienne */
    public function offres(int $id): array
    {
        /** @var list<OffreDuClient> */
        return $this->base->lire(
            'SELECT id, numero, titre, cree_le, archivee_le FROM offre
             WHERE client_id = :c AND artisan_id = :a AND supprimee_le IS NULL ORDER BY cree_le DESC, id DESC',
            ['c' => $id, 'a' => $this->portee->artisanId],
        );
    }

    /** @param array{nom: string, adresse: string|null, telephone: string|null, courriel: string|null} $f */
    public function creer(array $f, \DateTimeImmutable $quand): int
    {
        $this->base->ecrire(
            'INSERT INTO client (artisan_id, nom, adresse, telephone, courriel, cree_le) VALUES (:a, :n, :ad, :t, :c, :q)',
            ['a' => $this->portee->artisanId, 'n' => $f['nom'], 'ad' => $f['adresse'], 't' => $f['telephone'], 'c' => $f['courriel'],
             'q' => Dates::versBase($quand)],
        );
        return $this->base->dernierId();
    }

    /**
     * @param array{nom: string, adresse: string|null, telephone: string|null, courriel: string|null} $f
     * @return bool faux si la fiche n'est pas à l'artisan
     */
    public function modifier(int $id, array $f, \DateTimeImmutable $quand): bool
    {
        if ($this->parId($id) === null) {
            return false;
        }
        $this->base->ecrire(
            'UPDATE client SET nom = :n, adresse = :ad, telephone = :t, courriel = :c, modifie_le = :q WHERE id = :id AND artisan_id = :a',
            ['n' => $f['nom'], 'ad' => $f['adresse'], 't' => $f['telephone'], 'c' => $f['courriel'], 'q' => Dates::versBase($quand),
             'id' => $id, 'a' => $this->portee->artisanId],
        );
        return true;
    }

    /**
     * Supprime la fiche d'un client sans offre visible. Les offres supprimées qui la désignaient encore
     * reprennent son nom et son adresse comme texte : « Annuler » sur l'une d'elles ne la laisse pas sans client.
     *
     * @return bool faux si la fiche a des offres (ou n'est pas à l'artisan)
     */
    public function supprimer(int $id): bool
    {
        return (bool) $this->base->transaction(function () use ($id): bool {
            if ($this->parId($id) === null || $this->offres($id) !== []) {
                return false;
            }
            $this->base->ecrire(
                'UPDATE offre o JOIN client c ON c.id = o.client_id AND c.artisan_id = o.artisan_id
                 SET o.client_dicte = c.nom, o.lieu_dicte = c.adresse, o.client_id = NULL
                 WHERE o.client_id = :id AND o.artisan_id = :a',
                ['id' => $id, 'a' => $this->portee->artisanId],
            );
            $this->base->ecrire('DELETE FROM client WHERE id = :id AND artisan_id = :a', ['id' => $id, 'a' => $this->portee->artisanId]);
            return true;
        });
    }

    /** Toutes les fiches de l'artisan, après ses offres (réinitialisation d'un compte de démonstration). */
    public function toutEffacer(): void
    {
        $this->base->ecrire('UPDATE offre SET client_id = NULL WHERE artisan_id = :a', ['a' => $this->portee->artisanId]);
        $this->base->ecrire('DELETE FROM client WHERE artisan_id = :a', ['a' => $this->portee->artisanId]);
    }
}
