<?php

declare(strict_types=1);

namespace OffreGen\Compte;

use OffreGen\Adresse\Adresse;
use OffreGen\Audio\StockageAudio;
use OffreGen\Catalogue\CatalogueFictif;
use OffreGen\Donnees\Base;
use OffreGen\Donnees\DepotArtisans;
use OffreGen\Donnees\DepotClients;
use OffreGen\Donnees\DepotEnregistrements;
use OffreGen\Donnees\DepotFactures;
use OffreGen\Donnees\DepotOffres;
use OffreGen\Donnees\DepotProduits;
use OffreGen\Donnees\DepotReglages;
use OffreGen\Donnees\Portee;
use OffreGen\Offre\LigneExtraite;
use Psr\Clock\ClockInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

/**
 * Création des comptes et remise à zéro des comptes de démonstration, par les commandes de bin/ seulement.
 * Pas d'inscription en ligne.
 */
final class Comptes
{
    private const ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';

    public function __construct(
        private readonly Base $base,
        private readonly DepotArtisans $artisans,
        private readonly PasswordHasherInterface $hacheur,
        private readonly StockageAudio $stockage,
        private readonly ClockInterface $horloge,
    ) {
    }

    /**
     * @return string le mot de passe généré, à transmettre une fois à l'artisan ; il n'est gardé nulle part en clair
     */
    public function creer(string $courriel, string $entreprise, bool $demo, ?int $plafond): string
    {
        $courriel = DepotArtisans::normaliser($courriel);
        if (filter_var($courriel, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException("Courriel invalide : $courriel");
        }
        if (trim($entreprise) === '') {
            throw new \InvalidArgumentException('Entreprise manquante.');
        }
        if ($this->artisans->parCourriel($courriel) !== null) {
            throw new \InvalidArgumentException("Un compte existe déjà pour $courriel.");
        }
        if ($plafond !== null && $plafond < 1) {
            throw new \InvalidArgumentException('Le plafond quotidien doit valoir au moins 1.');
        }
        $motDePasse = self::genererMotDePasse();
        $this->artisans->creer($courriel, $this->hacheur->hash($motDePasse), trim($entreprise), $demo, $plafond, $this->horloge->now());
        return $motDePasse;
    }

    /**
     * Remet un compte de démonstration à son état d'origine : plus d'offre, plus d'enregistrement,
     * plus d'audio, le catalogue fictif du métier choisi. Refuse tout compte qui n'est pas de démonstration.
     *
     * @return int nombre de fichiers audio supprimés
     */
    public function reinitialiserDemo(string $courriel, string $metier): int
    {
        $artisan = $this->artisans->parCourriel($courriel) ?? throw new \InvalidArgumentException("Aucun compte pour $courriel.");
        if ($artisan['demo'] !== 1) {
            throw new \InvalidArgumentException("$courriel n'est pas un compte de démonstration : rien n'est effacé.");
        }
        $catalogue = CatalogueFictif::produits($metier);
        $portee = new Portee($artisan['id']);
        $enregistrements = new DepotEnregistrements($this->base, $portee);
        $fichiers = $enregistrements->fichiers();

        $exemples = CatalogueFictif::offres($metier);
        $this->base->transaction(function () use ($portee, $enregistrements, $catalogue, $exemples): void {
            // Les factures d'abord : elles désignent les offres.
            (new DepotFactures($this->base, $portee))->toutEffacer();
            $offres = new DepotOffres($this->base, $portee);
            $offres->toutEffacer();
            $clients = new DepotClients($this->base, $portee);
            $clients->toutEffacer();
            $enregistrements->toutEffacer();
            $produits = new DepotProduits($this->base, $portee);
            $produits->remplacerTout($catalogue);
            // Le prochain visiteur de la démonstration voit l'assistant du premier lancement.
            (new DepotReglages($this->base, $portee))->assistantARefaire();
            $parNom = array_column($produits->tous(), null, 'designation');
            // La plus ancienne d'abord : elle reçoit le numéro 1.
            usort($exemples, static fn (array $x, array $y): int => $y['il_y_a_jours'] <=> $x['il_y_a_jours']);
            $parNomClient = [];
            foreach ($exemples as $o) {
                $lignes = [];
                foreach ($o['lignes'] as $designation => $quantite) {
                    $p = $parNom[$designation];
                    $lignes[] = new LigneExtraite($p['designation'], $quantite * 1000, $p['unite'], $p['prix_centimes'], false, $p['id']);
                }
                $quand = $this->horloge->now()->modify("-{$o['il_y_a_jours']} days");
                // Les clients des exemples ont leur fiche : la démonstration montre l'onglet Clients rempli.
                $parNomClient[$o['client']] ??= $clients->creer(['nom' => $o['client'], 'adresse' => Adresse::depuisTexte($o['adresse']) ?? new Adresse(),
                    'telephone' => null, 'courriel' => null], $quand);
                $offres->creerExemple($o['titre'], $lignes, $quand, $parNomClient[$o['client']]);
            }
        });

        // Les fichiers après la validation : une erreur en base ne laisse pas des lignes sans leur audio.
        foreach ($fichiers as $fichier) {
            $this->stockage->supprimer($fichier);
        }
        return count($fichiers);
    }

    /** Quatre groupes de quatre caractères sans ambiguïté (ni 0/o, ni 1/l/i) : environ 79 bits. */
    public static function genererMotDePasse(): string
    {
        $groupes = [];
        for ($g = 0; $g < 4; $g++) {
            $groupe = '';
            for ($i = 0; $i < 4; $i++) {
                $groupe .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
            $groupes[] = $groupe;
        }
        return implode('-', $groupes);
    }
}
