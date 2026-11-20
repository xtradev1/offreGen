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
    public function creer(string $courriel, string $entreprise, bool $demo, ?int $plafond, ?int $offresMax = null, ?string $metier = null, ?string $metierSaisi = null): string
    {
        $metierSaisi = self::verifierMetier($metier, $metierSaisi);
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
        self::verifierOffresMax($offresMax);
        $motDePasse = self::genererMotDePasse();
        $hache = $this->hacheur->hash($motDePasse);
        $this->base->transaction(function () use ($courriel, $hache, $entreprise, $demo, $plafond, $offresMax, $metier, $metierSaisi): void {
            $id = $this->artisans->creer($courriel, $hache, trim($entreprise), $demo, $plafond, $this->horloge->now(), $offresMax);
            if ($metier === null) {
                return;
            }
            if ($demo) {
                // Une démonstration créée avec son métier est posée tout de suite : catalogue chiffré, offres d'exemple.
                $this->reinitialiserDemo($courriel, $metier, $metierSaisi);
                return;
            }
            // Un essai part des désignations de son métier, tous les prix à 0 ; le prix de la démonstration attend
            // à côté, proposé par l'écran « Vos prix ».
            $this->artisans->changerMetier($id, $metier, $metierSaisi);
            (new DepotProduits($this->base, new Portee($id)))->remplacerTout(self::exemples(CatalogueFictif::produits($metier), true));
        });
        return $motDePasse;
    }

    /**
     * Une démonstration devient un essai, en une transaction : les offres d'exemple, leurs factures et les fiches
     * d'exemple sans offre partent ; les produits d'exemple jamais modifiés repartent à 0 ; ce que l'artisan a
     * dicté, créé ou modifié reste, comme ses réglages. Le compte reçoit $offresMax offres en plus de celles
     * déjà dictées pendant la démonstration. Refuse tout compte qui n'est pas de démonstration.
     *
     * Une démonstration a une adresse inventée et un mot de passe partagé (revue du 2026-11-20) : le compte prend
     * l'adresse de l'artisan, reçoit un mot de passe neuf, et les sessions ouvertes pendant la démonstration
     * tombent. Une démonstration posée avant les marques « exemple » (sans métier) est refusée : rien n'y
     * distingue les exemples, il faut la remettre à zéro d'abord.
     *
     * @param string $courriel l'adresse de la démonstration
     * @param string $courrielArtisan l'adresse de l'artisan, qui devient celle du compte
     * @return array{offres: int, clients: int, produits: int, dictees: int, mot_de_passe: string} offres et fiches
     *         d'exemple effacées, produits remis à 0, visites dictées gardées, mot de passe à transmettre une fois
     */
    public function passerEnEssai(string $courriel, string $courrielArtisan, int $offresMax = 10): array
    {
        self::verifierOffresMax($offresMax);
        $artisan = $this->artisans->parCourriel($courriel) ?? throw new \InvalidArgumentException("Aucun compte pour $courriel.");
        if ($artisan['demo'] !== 1) {
            throw new \InvalidArgumentException("$courriel n'est pas un compte de démonstration : rien n'est changé.");
        }
        if ($artisan['metier'] === null) {
            throw new \InvalidArgumentException("$courriel a été posée avant les marques d'exemple : la remettre à zéro (bin/reinitialiser-demo), puis la passer à l'essai.");
        }
        $courrielArtisan = DepotArtisans::normaliser($courrielArtisan);
        if (filter_var($courrielArtisan, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException("Courriel invalide : $courrielArtisan");
        }
        if ($courrielArtisan !== $artisan['courriel'] && $this->artisans->parCourriel($courrielArtisan) !== null) {
            throw new \InvalidArgumentException("Un compte existe déjà pour $courrielArtisan.");
        }
        $motDePasse = self::genererMotDePasse();
        $hache = $this->hacheur->hash($motDePasse);
        $portee = new Portee($artisan['id']);
        return $this->base->transaction(function () use ($artisan, $portee, $offresMax, $courrielArtisan, $hache, $motDePasse): array {
            // Les factures d'abord : elles désignent les offres. Les fiches après : les offres les désignent.
            (new DepotFactures($this->base, $portee))->effacerDesOffresExemple();
            $dictees = (new DepotEnregistrements($this->base, $portee))->recus();
            $bilan = [
                'offres' => (new DepotOffres($this->base, $portee))->effacerExemples(),
                'clients' => (new DepotClients($this->base, $portee))->effacerExemplesSansOffre(),
                'produits' => (new DepotProduits($this->base, $portee))->remettreExemplesAZero(),
                'dictees' => $dictees,
                'mot_de_passe' => $motDePasse,
            ];
            // Le plafond d'un essai compte tous les imports du compte : ceux de la démonstration ne sont pas décomptés.
            // Faux : le compte n'est plus une démonstration (remise à zéro ou passage lancés en même temps).
            if (!$this->artisans->sortirDeDemo($artisan['id'], $offresMax + $dictees, $courrielArtisan)) {
                throw new \RuntimeException("{$artisan['courriel']} n'est plus une démonstration : rien n'est changé.");
            }
            // Le mot de passe de la démonstration ne sert plus, et le numéro de session avance.
            $this->artisans->remplacerMotDePasse($artisan['id'], $hache);
            return $bilan;
        });
    }

    /**
     * Le métier est une clé de CatalogueFictif ; le texte saisi ne va qu'avec « autre », 60 caractères au plus.
     *
     * @return string|null le texte saisi, sans espaces autour ; null s'il est vide
     */
    private static function verifierMetier(?string $metier, ?string $metierSaisi): ?string
    {
        $metierSaisi ??= '';
        // Une entrée d'utilisateur : ni octets illisibles, ni caractères de commande (saut de ligne, séquence
        // d'échappement), que bin/metiers-saisis imprimerait tels quels dans un terminal.
        if (!mb_check_encoding($metierSaisi, 'UTF-8')) {
            throw new \InvalidArgumentException('Le texte du métier est illisible.');
        }
        $metierSaisi = (string) preg_replace('/^[\s\x{00A0}]+|[\s\x{00A0}]+$/u', '', $metierSaisi);
        if (preg_match('/\p{C}/u', $metierSaisi) === 1) {
            throw new \InvalidArgumentException('Le texte du métier ne contient que des lettres, des chiffres et de la ponctuation.');
        }
        if ($metier !== null) {
            CatalogueFictif::libelle($metier);
        }
        if ($metierSaisi === '') {
            return null;
        }
        if ($metier !== 'autre') {
            throw new \InvalidArgumentException('Le texte du métier ne va qu\'avec le métier « autre ».');
        }
        if (mb_strlen($metierSaisi) > 60) {
            throw new \InvalidArgumentException('Le texte du métier fait 60 caractères au plus.');
        }
        return $metierSaisi;
    }

    /**
     * Les produits d'un catalogue fictif, marqués « exemple » par leur prix de démonstration.
     *
     * @param list<array{categorie: string, designation: string, unite: string, prix_centimes: int, synonymes: string|null}> $catalogue
     * @return list<array{categorie: string, designation: string, unite: string, prix_centimes: int, synonymes: string|null, prix_exemple_centimes: int}>
     */
    private static function exemples(array $catalogue, bool $prixAZero): array
    {
        return array_map(
            static fn (array $p): array => ['prix_centimes' => $prixAZero ? 0 : $p['prix_centimes'], 'prix_exemple_centimes' => $p['prix_centimes']] + $p,
            $catalogue,
        );
    }

    /** Donne plus d'offres à un compte d'essai, ou lève son plafond (null). */
    public function changerOffresMax(string $courriel, ?int $offresMax): void
    {
        self::verifierOffresMax($offresMax);
        $artisan = $this->artisans->parCourriel($courriel) ?? throw new \InvalidArgumentException("Aucun compte pour $courriel.");
        $this->artisans->changerOffresMax($artisan['id'], $offresMax);
    }

    /** Donne ou retire le droit administrateur (bin/administrateur). */
    public function changerAdministrateur(string $courriel, bool $administrateur): void
    {
        $artisan = $this->artisans->parCourriel($courriel) ?? throw new \InvalidArgumentException("Aucun compte pour $courriel.");
        $this->artisans->changerAdministrateur($artisan['id'], $administrateur);
    }

    private static function verifierOffresMax(?int $offresMax): void
    {
        if ($offresMax !== null && $offresMax < 1) {
            throw new \InvalidArgumentException('Le nombre d\'offres d\'un compte d\'essai doit valoir au moins 1.');
        }
    }

    /**
     * Remet un compte de démonstration à son état d'origine : plus d'offre, plus d'enregistrement,
     * plus d'audio, le catalogue fictif du métier choisi. Refuse tout compte qui n'est pas de démonstration.
     *
     * @return int nombre de fichiers audio supprimés
     */
    public function reinitialiserDemo(string $courriel, string $metier, ?string $metierSaisi = null): int
    {
        $artisan = $this->artisans->parCourriel($courriel) ?? throw new \InvalidArgumentException("Aucun compte pour $courriel.");
        if ($artisan['demo'] !== 1) {
            throw new \InvalidArgumentException("$courriel n'est pas un compte de démonstration : rien n'est effacé.");
        }
        $metierSaisi = self::verifierMetier($metier, $metierSaisi);
        $catalogue = self::exemples(CatalogueFictif::produits($metier), false);
        $portee = new Portee($artisan['id']);
        $enregistrements = new DepotEnregistrements($this->base, $portee);
        $fichiers = $enregistrements->fichiers();

        $exemples = CatalogueFictif::offres($metier);
        $this->base->transaction(function () use ($artisan, $metier, $metierSaisi, $portee, $enregistrements, $catalogue, $exemples): void {
            $this->artisans->changerMetier($artisan['id'], $metier, $metierSaisi);
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
                    'telephone' => null, 'courriel' => null, 'exemple' => true], $quand);
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
