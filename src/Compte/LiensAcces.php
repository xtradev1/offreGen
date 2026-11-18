<?php

declare(strict_types=1);

namespace OffreGen\Compte;

use OffreGen\Donnees\Base;
use OffreGen\Donnees\DepotArtisans;
use OffreGen\Donnees\DepotLiensAcces;
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
    public const DEMANDES_PAR_HEURE = 3;
    private const DUREES = [self::OUBLI => '+1 hour', self::ACCES => '+7 days'];
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
        // Textes relus par l'agent XtraDev le 2026-11-18 (corrections 1 et 5 appliquées).
        $this->messagerie->envoyer(
            $artisan['courriel'],
            "Choisir un nouveau mot de passe pour {$this->nomApplication}",
            "Bonjour,\r\n\r\nPour choisir un nouveau mot de passe, ouvrez ce lien dans l'heure\u{00A0}:\r\n$lien\r\n\r\n"
            . "Le lien sert une seule fois. Passé ce délai, refaites la demande depuis la page de connexion\u{00A0}: {$this->connexion()}\r\n\r\n"
            . "Vous n'avez rien demandé\u{00A0}? Ignorez ce courriel, votre mot de passe ne change pas.\r\n\r\n{$this->nomApplication}",
        );
    }

    /** Accès d'un compte que Xavier vient de créer : l'artisan choisit lui-même son mot de passe (A3 = A). */
    public function envoyerAcces(string $courriel): void
    {
        $artisan = $this->artisans->parCourriel($courriel) ?? throw new \InvalidArgumentException("Aucun compte pour $courriel.");
        if ($artisan['demo'] === 1) {
            throw new \InvalidArgumentException("$courriel est un compte de démonstration : aucun courriel ne part.");
        }
        $lien = $this->lien($this->emettre($artisan['id'], self::ACCES));
        $this->messagerie->envoyer(
            $artisan['courriel'],
            "Votre accès à {$this->nomApplication}",
            "Bonjour,\r\n\r\nUn compte {$this->nomApplication} est ouvert pour {$artisan['entreprise']} avec votre adresse électronique. "
            . "Pour choisir votre mot de passe et vous connecter, ouvrez ce lien dans les 7\u{00A0}jours\u{00A0}:\r\n$lien\r\n\r\n"
            . "Le lien sert une seule fois. Passé ce délai, demandez un nouveau lien avec «\u{00A0}Mot de passe oublié\u{00A0}» sur {$this->connexion()}\r\n\r\n{$this->nomApplication}",
        );
    }

    public function artisanDuLien(string $jeton): ?int
    {
        if (preg_match(self::FORMAT_JETON, $jeton) !== 1) {
            return null;
        }
        return $this->liens->valide(self::empreinte($jeton), $this->horloge->now())['artisan_id'] ?? null;
    }

    /**
     * @return array{artisan: int, version: int}|null le compte et son nouveau numéro de session ; null si le lien
     *     a expiré, a déjà servi ou n'existe pas
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
            if ($lien === null || $this->liens->marquerUtilise($lien['id'], $maintenant) !== 1) {
                return null;
            }
            $this->liens->annulerTout($lien['artisan_id'], $maintenant);
            $version = $this->artisans->remplacerMotDePasse($lien['artisan_id'], $hache)
                ?? throw new \LogicException('Mot de passe non remplacé sans condition de version.');
            return ['artisan' => $lien['artisan_id'], 'version' => $version];
        });
    }

    /** Mot de passe changé dans les réglages : un lien encore ouvert ne doit plus le remplacer. */
    public function annulerTout(int $artisanId): void
    {
        $this->liens->annulerTout($artisanId, $this->horloge->now());
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

    private function connexion(): string
    {
        return rtrim($this->adresseApplication, '/') . '/connexion';
    }

    private static function empreinte(string $jeton): string
    {
        return hash('sha256', $jeton);
    }
}
