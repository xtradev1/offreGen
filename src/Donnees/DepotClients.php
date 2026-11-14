<?php

declare(strict_types=1);

namespace OffreGen\Donnees;

use OffreGen\Adresse\Adresse;
use OffreGen\Client\Rapprochement;

/**
 * Les clients d'un artisan. Une fiche naît quand l'artisan la crée ou confirme le nom dicté (règle de Xavier
 * du 2026-11-12) ; elle ne se supprime que si aucune offre visible ne la désigne.
 *
 * L'adresse est en champs séparés (rue, numero, npa, localite, pays). La colonne `adresse` en garde le texte
 * (Adresse::lignes), écrit ici seulement, pour l'écran de l'offre et le document ; une fiche d'avant les
 * champs séparés que la migration n'a pas su découper y garde son ancien texte jusqu'à ce que l'adresse soit
 * tapée en champs.
 *
 * @phpstan-type Client array{id: int, nom: string, adresse: string|null, rue: string|null, numero: string|null, npa: string|null, localite: string|null, pays: string|null, telephone: string|null, courriel: string|null}
 * @phpstan-type ClientListe array{id: int, nom: string, adresse: string|null, rue: string|null, numero: string|null, npa: string|null, localite: string|null, pays: string|null, telephone: string|null, courriel: string|null, nb_offres: int, derniere: string|null}
 * @phpstan-type OffreDuClient array{id: int, numero: int, titre: string, cree_le: string, archivee_le: string|null}
 * @phpstan-type FicheClient array{nom: string, adresse: Adresse, ancienne?: string|null, telephone: string|null, courriel: string|null}
 */
final class DepotClients
{
    private const COLONNES = 'c.id, c.nom, c.adresse, c.rue, c.numero, c.npa, c.localite, c.pays, c.telephone, c.courriel';

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
             WHERE c.artisan_id = :a GROUP BY ' . self::COLONNES,
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
        /** @var list<array{id: int, nom: string, npa: string|null, localite: string|null}> */
        return $this->base->lire('SELECT id, nom, npa, localite FROM client WHERE artisan_id = :a', ['a' => $this->portee->artisanId]);
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

    /** @param FicheClient $f « ancienne » : un lieu dicté qui ne se découpe pas, gardé en texte à reporter */
    public function creer(array $f, \DateTimeImmutable $quand): int
    {
        $this->base->ecrire(
            'INSERT INTO client (artisan_id, nom, rue, numero, npa, localite, pays, adresse, telephone, courriel, cree_le)
             VALUES (:a, :n, :rue, :numero, :npa, :localite, :pays, :ad, :t, :c, :q)',
            $f['adresse']->versBase('') + ['a' => $this->portee->artisanId, 'n' => $f['nom'], 'ad' => self::texte($f['adresse']) ?? ($f['ancienne'] ?? null),
             't' => $f['telephone'], 'c' => $f['courriel'], 'q' => Dates::versBase($quand)],
        );
        return $this->base->dernierId();
    }

    /**
     * @param FicheClient $f
     * @return bool faux si la fiche n'est pas à l'artisan
     */
    public function modifier(int $id, array $f, \DateTimeImmutable $quand): bool
    {
        $avant = $this->parId($id);
        if ($avant === null) {
            return false;
        }
        // Sans adresse en champs, ni avant ni maintenant : le texte d'avant les champs séparés reste à reprendre.
        $texte = $f['adresse']->estVide() && $avant['npa'] === null ? $avant['adresse'] : self::texte($f['adresse']);
        $this->base->ecrire(
            'UPDATE client SET nom = :n, rue = :rue, numero = :numero, npa = :npa, localite = :localite, pays = :pays, adresse = :ad,
                    telephone = :t, courriel = :c, modifie_le = :q
             WHERE id = :id AND artisan_id = :a',
            $f['adresse']->versBase('') + ['n' => $f['nom'], 'ad' => $texte, 't' => $f['telephone'], 'c' => $f['courriel'],
             'q' => Dates::versBase($quand), 'id' => $id, 'a' => $this->portee->artisanId],
        );
        return true;
    }

    /**
     * L'adresse d'une fiche, en champs (vide pour une fiche d'avant les champs séparés non découpée).
     *
     * @param array<string, mixed> $k
     */
    public static function adresse(array $k): Adresse
    {
        return Adresse::depuisBase($k, '');
    }

    private static function texte(Adresse $a): ?string
    {
        return $a->estVide() ? null : $a->lignes();
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
