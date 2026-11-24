<?php

declare(strict_types=1);

namespace OffreGen\Compte;

use OffreGen\Donnees\Base;
use OffreGen\Donnees\DepotArtisans;
use OffreGen\Donnees\DepotLiensAcces;
use OffreGen\Systeme\Courriel;
use OffreGen\Systeme\Messagerie;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

/**
 * Liens envoyés par courriel pour choisir un mot de passe : « mot de passe oublié » (une heure) et accès d'un nouveau
 * compte (sept jours, A3 = A). Un lien sert une fois ; s'en servir ferme les autres liens du compte et les sessions
 * ouvertes ailleurs. Le jeton (256 bits) n'est gardé que sous forme d'empreinte.
 */
final class LiensAcces
{
    public const OUBLI = 'oubli';
    public const ACCES = 'acces';
    /** Entrée dans une démo : par un code QR (une heure) ou par un message (24 heures). Aucun mot de passe. */
    public const DEMO_QR = 'demo_qr';
    public const DEMO_MSG = 'demo_msg';
    /** « Revenir dans votre démo », envoyé au courriel du prospect : sert plusieurs fois, jusqu'à la fin de la démo. */
    public const DEMO_RETOUR = 'demo_ret';
    private const MOTIFS_DEMO = [self::DEMO_QR, self::DEMO_MSG, self::DEMO_RETOUR];
    public const DEMANDES_PAR_HEURE = 3;
    private const DUREES = [self::OUBLI => '+1 hour', self::ACCES => '+7 days', self::DEMO_QR => '+1 hour', self::DEMO_MSG => '+24 hours'];
    private const FORMAT_JETON = '/^[A-Za-z0-9_-]{43}$/';

    public function __construct(
        private readonly Base $base,
        private readonly DepotLiensAcces $liens,
        private readonly DepotArtisans $artisans,
        private readonly Messagerie $messagerie,
        private readonly PasswordHasherInterface $hacheur,
        private readonly ClockInterface $horloge,
        private readonly LoggerInterface $journal,
        private readonly string $adresseApplication,
        private readonly string $nomApplication,
    ) {
    }

    /**
     * Rien ne dit à l'appelant si le compte existe : appelé après la réponse, il ne change ni la page ni son délai.
     * Ni courriel inconnu, ni compte de démonstration (adresse inventée), ni plus de trois demandes par heure.
     */
    public function demanderOubli(string $courriel): void
    {
        $artisan = $this->artisans->parCourriel($courriel);
        if ($artisan === null || $artisan['demo'] === 1) {
            return;
        }
        $maintenant = $this->horloge->now();
        if ($this->liens->compterDepuis($artisan['id'], self::OUBLI, $maintenant->modify('-1 hour')) >= self::DEMANDES_PAR_HEURE) {
            $this->journal->info("offreGen, mot de passe oublié : plafond de demandes atteint pour le compte {$artisan['id']}.");
            return;
        }
        $lien = $this->lien($this->emettre($artisan['id'], self::OUBLI));
        // Textes relus par l'agent XtraDev les 2026-11-18 et 2026-11-23 ; mise en forme : maquette v8, validée le 23.11.
        (new Courriel(
            "Choisir un nouveau mot de passe pour {$this->nomApplication}",
            'Nouveau mot de passe',
            "Pour choisir un nouveau mot de passe, ouvrez ce lien dans l'heure\u{00A0}:",
            'Choisir mon mot de passe',
            $lien,
            ['Le lien sert une seule fois.'],
            "Vous n'avez rien demandé\u{00A0}? Ignorez ce courriel, votre mot de passe ne change pas.",
        ))->envoyer($this->messagerie, $artisan['courriel'], $this->nomApplication, $this->adresseApplication);
    }

    /** Accès d'un compte que Xavier vient de créer : l'artisan choisit lui-même son mot de passe (A3 = A). */
    public function envoyerAcces(string $courriel): void
    {
        $artisan = $this->artisans->parCourriel($courriel) ?? throw new \InvalidArgumentException("Aucun compte pour $courriel.");
        if ($artisan['demo'] === 1) {
            throw new \InvalidArgumentException("$courriel est un compte de démonstration : aucun courriel ne part.");
        }
        $lien = $this->lien($this->emettre($artisan['id'], self::ACCES));
        (new Courriel(
            "Votre accès à {$this->nomApplication}",
            "Votre compte {$this->nomApplication} est ouvert",
            "Un compte est ouvert pour {$artisan['entreprise']} avec votre adresse. Choisissez votre mot de passe dans les 7\u{00A0}jours\u{00A0}:",
            'Choisir mon mot de passe',
            $lien,
            ["Le lien sert une seule fois. Passé ce délai, demandez-en un nouveau avec «\u{00A0}Mot de passe oublié\u{00A0}», sur la page de connexion."],
        ))->envoyer($this->messagerie, $artisan['courriel'], $this->nomApplication, $this->adresseApplication);
    }

    /**
     * Le lien d'accès lui-même, sans courriel : pour un site qui n'envoie pas de courriels, l'administrateur le
     * transmet à la personne (D6, 2026-11-19). Même jeton qu'envoyerAcces : 7 jours, une seule fois.
     */
    public function lienDAcces(int $artisanId): string
    {
        return $this->lien($this->emettre($artisanId, self::ACCES));
    }

    public function artisanDuLien(string $jeton): ?int
    {
        if (preg_match(self::FORMAT_JETON, $jeton) !== 1) {
            return null;
        }
        $lien = $this->liens->valide(self::empreinte($jeton), $this->horloge->now());
        // Un lien de démo ouvre une démo : il ne sert jamais à choisir un mot de passe.
        return $lien === null || in_array($lien['motif'], self::MOTIFS_DEMO, true) ? null : $lien['artisan_id'];
    }

    /**
     * @return array{artisan: int, version: int, motif: string}|null le compte, son nouveau numéro de session et le motif
     *     du lien (oubli ou accès) ; null si le lien a expiré, a déjà servi ou n'existe pas
     */
    public function utiliser(string $jeton, string $motDePasse): ?array
    {
        // Le hachage coûte 64 Mo et 50 ms : jamais pour un lien qui ne vaut rien (envois en rafale de jetons inventés).
        if ($this->artisanDuLien($jeton) === null) {
            return null;
        }
        $hache = $this->hacheur->hash($motDePasse);
        $maintenant = $this->horloge->now();
        return $this->base->transaction(function () use ($jeton, $hache, $maintenant): ?array {
            $lien = $this->liens->valide(self::empreinte($jeton), $maintenant);
            if ($lien === null || in_array($lien['motif'], self::MOTIFS_DEMO, true) || $this->liens->marquerUtilise($lien['id'], $maintenant) !== 1) {
                return null;
            }
            $this->liens->annulerTout($lien['artisan_id'], $maintenant);
            $version = $this->artisans->remplacerMotDePasse($lien['artisan_id'], $hache)
                ?? throw new \LogicException('Mot de passe non remplacé sans condition de version.');
            return ['artisan' => $lien['artisan_id'], 'version' => $version, 'motif' => $lien['motif']];
        });
    }

    /**
     * Le mot de passe vient de changer (réglages ou « Mot de passe oublié », Xavier, 2026-11-24). Pas pour le premier
     * choix d'un mot de passe (lien d'accès) : rien n'a changé pour la personne. Rien pour une démonstration.
     */
    public function prevenirMotDePasseChange(int $artisanId): void
    {
        $artisan = $this->artisans->parId($artisanId);
        if ($artisan === null || $artisan['demo'] === 1) {
            return;
        }
        Courriel::motDePasseChange($this->horloge->now(), rtrim($this->adresseApplication, '/') . '/mot-de-passe/oubli')
            ->envoyer($this->messagerie, $artisan['courriel'], $this->nomApplication, $this->adresseApplication);
    }

    /** Mot de passe changé dans les réglages : un lien encore ouvert ne doit plus le remplacer. */
    public function annulerTout(int $artisanId): void
    {
        $this->liens->annulerTout($artisanId, $this->horloge->now());
    }

    /**
     * Le lien d'entrée dans une démo, à mettre dans un code QR ou dans un message. L'appelant a vérifié que le compte
     * est une démo de sa portée.
     */
    public function emettreDemo(int $artisanId, string $motif): string
    {
        if (!in_array($motif, [self::DEMO_QR, self::DEMO_MSG], true)) {
            throw new \InvalidArgumentException('Motif de lien de démo inconnu.');
        }
        return rtrim($this->adresseApplication, '/') . '/d/' . $this->emettre($artisanId, $motif);
    }

    /** La démo qu'ouvre ce lien, sans le consommer ; null s'il a expiré, a servi, ou n'est pas un lien de démo. */
    public function demoDuLien(string $jeton): ?int
    {
        if (preg_match(self::FORMAT_JETON, $jeton) !== 1) {
            return null;
        }
        $lien = $this->liens->valide(self::empreinte($jeton), $this->horloge->now());
        return $lien !== null && in_array($lien['motif'], self::MOTIFS_DEMO, true) ? $lien['artisan_id'] : null;
    }

    /**
     * Consomme le lien d'une démo. Le mot de passe et les sessions du compte ne changent pas : le lien ouvre une
     * session de plus. Les autres liens de la démo restent bons (un code montré, puis un message envoyé). Le lien de
     * retour, lui, n'est pas consommé : il sert jusqu'à la fin de la démo.
     *
     * @return array{artisan: int, version: int, motif: string}|null null si le lien a expiré, a déjà servi ou
     *         n'ouvre pas une démo
     */
    public function ouvrirDemo(string $jeton): ?array
    {
        if ($this->demoDuLien($jeton) === null) {
            return null;
        }
        $maintenant = $this->horloge->now();
        return $this->base->transaction(function () use ($jeton, $maintenant): ?array {
            $lien = $this->liens->valide(self::empreinte($jeton), $maintenant);
            if ($lien === null || !in_array($lien['motif'], self::MOTIFS_DEMO, true)) {
                return null;
            }
            $artisan = $this->artisans->parId($lien['artisan_id']);
            if ($artisan === null || $artisan['demo'] !== 1) {
                return null;
            }
            if ($lien['motif'] !== self::DEMO_RETOUR && $this->liens->marquerUtilise($lien['id'], $maintenant) !== 1) {
                return null;
            }
            return ['artisan' => $artisan['id'], 'version' => $artisan['version_session'], 'motif' => $lien['motif']];
        });
    }

    /**
     * Le courriel « revenir dans votre démo » (Xavier, 2026-11-20 : le prospect entre directement, « et puis qu'il
     * reçoive automatiquement un mail à son adresse », avec « un lien d'accès à la démo »). C'est le seul courriel
     * qu'une démo fait partir : il remplace, pour lui seul, la règle du 18.11. L'adresse n'est pas confirmée : le
     * message ne donne accès qu'à la démo, sans mot de passe à choisir. Un lien de retour plus ancien cesse de
     * valoir (courriel corrigé). $presentePar : la personne qui a présenté la démo, qui signe le courriel.
     */
    public function envoyerRetourDemo(int $artisanId, string $courriel, \DateTimeImmutable $fin, ?string $presentePar = null): void
    {
        $maintenant = $this->horloge->now();
        if ($fin <= $maintenant) {
            return;
        }
        $this->liens->annulerMotif($artisanId, self::DEMO_RETOUR, $maintenant);
        $lien = $this->lienRetourDemo($artisanId, $fin);
        (new Courriel(
            "Votre démo {$this->nomApplication}",
            "Votre démo reste ouverte jusqu'au " . Courriel::moment($fin),
            "Pour y revenir, sur votre téléphone ou sur un ordinateur\u{00A0}:",
            'Revenir dans ma démo',
            $lien,
            ["Ce lien ouvre votre démo sans mot de passe\u{00A0}: gardez-le pour vous."],
            "Vous n'avez pas essayé {$this->nomApplication}\u{00A0}? Ignorez ce courriel.",
            $presentePar,
        ))->envoyer($this->messagerie, $courriel, $this->nomApplication, $this->adresseApplication);
    }

    /**
     * Un lien de retour de plus, valable jusqu'à la fin de la démo, sans fermer les précédents : un rappel ne doit pas
     * rendre muet le premier courriel que le prospect a gardé.
     */
    public function lienRetourDemo(int $artisanId, \DateTimeImmutable $fin): string
    {
        $jeton = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $this->liens->creer($artisanId, self::empreinte($jeton), self::DEMO_RETOUR, $this->horloge->now(), $fin);
        return rtrim($this->adresseApplication, '/') . '/d/' . $jeton;
    }

    /** Vrai si un lien de retour a déjà été envoyé pour cette démo. */
    public function retourDemoEnvoye(int $artisanId): bool
    {
        return $this->liens->compterDepuis($artisanId, self::DEMO_RETOUR, $this->horloge->now()->modify('-60 days')) > 0;
    }

    private function emettre(int $artisanId, string $motif): string
    {
        $jeton = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $maintenant = $this->horloge->now();
        $this->liens->creer($artisanId, self::empreinte($jeton), $motif, $maintenant, $maintenant->modify(self::DUREES[$motif]));
        return $jeton;
    }

    private function lien(string $jeton): string
    {
        return rtrim($this->adresseApplication, '/') . '/mot-de-passe/' . $jeton;
    }


    private static function empreinte(string $jeton): string
    {
        return hash('sha256', $jeton);
    }
}
