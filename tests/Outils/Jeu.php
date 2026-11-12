<?php

declare(strict_types=1);

namespace OffreGen\Tests\Outils;

use OffreGen\Donnees\Base;
use OffreGen\Donnees\DepotArtisans;
use OffreGen\Donnees\DepotClients;
use OffreGen\Donnees\DepotEnregistrements;
use OffreGen\Donnees\DepotOffres;
use OffreGen\Donnees\DepotProduits;
use OffreGen\Donnees\Portee;
use OffreGen\Offre\LigneExtraite;

/**
 * Données de test : deux artisans A et B, chacun avec un produit, un enregistrement, une offre et une fiche client
 * (l'offre ne la désigne pas : elle garde le cas « client à choisir »).
 */
final class Jeu
{
    public const MOT_DE_PASSE = 'essai-correct-1234';

    public readonly Base $base;
    public int $a;
    public int $b;
    /** @var array<string, string> */
    public array $uuid = [];
    /** @var array<string, int> */
    public array $offre = [];
    /** @var array<string, int> */
    public array $enregistrement = [];
    /** @var array<string, int> */
    public array $produit = [];
    /** @var array<string, int> */
    public array $client = [];

    private static ?string $hache = null;

    public function __construct(public readonly \DateTimeImmutable $maintenant = new \DateTimeImmutable('2026-11-07 10:00:00', new \DateTimeZone('UTC')))
    {
        BaseDeTest::vider();
        $this->base = new Base(BaseDeTest::pdo());
        // Un seul hachage argon2id pour toute la suite : il coûte environ 50 ms.
        self::$hache ??= password_hash(self::MOT_DE_PASSE, PASSWORD_ARGON2ID);
        $artisans = new DepotArtisans($this->base);
        $this->a = $artisans->creer('a@exemple.ch', self::$hache, 'Entreprise A', false, null, $maintenant);
        $this->b = $artisans->creer('b@exemple.ch', self::$hache, 'Entreprise B', false, null, $maintenant);
        foreach (['a' => $this->a, 'b' => $this->b] as $nom => $id) {
            $this->remplir($nom, new Portee($id));
        }
        // Comptes déjà réglés : l'assistant du premier lancement ne s'ouvre pas (testé à part).
        BaseDeTest::pdo()->exec("UPDATE artisan SET reglages_faits_le = '2026-11-01 08:00:00' WHERE id IN ($this->a, $this->b)");
    }

    private function remplir(string $nom, Portee $portee): void
    {
        $produits = new DepotProduits($this->base, $portee);
        $produits->remplacerTout([['categorie' => 'Peinture', 'designation' => "Peinture murale $nom", 'unite' => 'm²', 'prix_centimes' => 1850]]);
        $this->produit[$nom] = $produits->tous()[0]['id'];
        $this->uuid[$nom] = sprintf('00000000-0000-4000-8000-00000000000%s', $nom === 'a' ? '1' : '2');
        $this->enregistrement[$nom] = (new DepotEnregistrements($this->base, $portee))
            ->creer($this->uuid[$nom], "visite-$nom.m4a", 'audio/mp4', 1000, "$portee->artisanId/{$this->uuid[$nom]}.m4a", $this->maintenant);
        $this->client[$nom] = (new DepotClients($this->base, $portee))->creer(
            ['nom' => "Client $nom", 'adresse' => "Rue $nom 1\n2800 Delémont", 'telephone' => null, 'courriel' => null], $this->maintenant);
        $this->offre[$nom] = (new DepotOffres($this->base, $portee))->creerDepuisEnregistrement(
            $this->enregistrement[$nom],
            "Offre $nom",
            [new LigneExtraite("Peinture murale $nom", 12500, 'm²', 1850, false, $this->produit[$nom])],
            $this->maintenant,
        );
    }
}
