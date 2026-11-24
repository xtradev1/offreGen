<?php

declare(strict_types=1);

namespace OffreGen\Systeme;

use OffreGen\Donnees\DepotEtapes;
use OffreGen\Donnees\FileTraitement;
use OffreGen\Donnees\VerrouPerdu;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;

/**
 * Chronomètre des dictées (sous-chantier 31) : une ligne par étape et par passage dans `dictee_etape`, écrite au début de
 * l'étape et complétée à la fin. Un passage mort laisse ses étapes en cours ; le passage suivant les marque interrompues
 * et écrit la reprise. Sans passage ouvert (bancs, outils, tests), tout est sans effet.
 *
 * Il porte aussi le pouls du verrou (décision D4 de Xavier, 2026-11-24) : le verrou se prend pour BAIL_SECONDES et le
 * pouls le prolonge, au plus une fois toutes les POULS_SECONDES, à chaque étape et pendant les appels HTTP
 * (on_progress, appelé au moins une fois par seconde : preuve dans l'ETAT du sous-chantier). Un traitement mort ne
 * prolonge plus rien : le cron le reprend 2 min 30 plus tard au lieu de 15 minutes.
 *
 * Une écriture du chronomètre qui échoue n'arrête jamais une dictée : elle est notée au journal. Seul le pouls arrête,
 * quand le verrou a été repris par un autre traitement (VerrouPerdu).
 */
final class Chronometre
{
    public const WEB = 'web';
    public const TELEPHONE = 'telephone';
    public const APRES_REPONSE = 'apres_reponse';
    public const CRON = 'cron';
    public const REJEU = 'rejeu';

    /** Durée du verrou d'un traitement (Xavier, D5 : « 2 minutes 30 pour avoir un peu de marge »). */
    public const BAIL_SECONDES = 150;
    public const POULS_SECONDES = 20;

    /** @var array{enregistrement: int, rejeu: int|null, uuid: string|null, jeton: string|null, tentative: int, processus: string}|null */
    private ?array $passage = null;
    private ?\DateTimeImmutable $dernierPouls = null;
    private bool $journalise = false;
    /** @var array<int, array{etape: string, debut: \DateTimeImmutable}> étapes ouvertes par ce passage, pas encore fermées */
    private array $ouvertes = [];

    /** @var (\Closure(): DepotEtapes)|DepotEtapes|null */
    private \Closure|DepotEtapes|null $etapes;
    /** @var (\Closure(): FileTraitement)|FileTraitement|null */
    private \Closure|FileTraitement|null $file;

    /**
     * Les dépôts peuvent venir d'une fonction, appelée à la première écriture : construire l'extracteur ou le
     * transcripteur n'ouvre pas la connexion à la base (tests/Http/ConteneurTest).
     *
     * @param (\Closure(): DepotEtapes)|DepotEtapes|null $etapes
     * @param (\Closure(): FileTraitement)|FileTraitement|null $file
     */
    public function __construct(
        \Closure|DepotEtapes|null $etapes,
        \Closure|FileTraitement|null $file,
        private readonly ClockInterface $horloge,
        private readonly LoggerInterface $journal,
    ) {
        $this->etapes = $etapes;
        $this->file = $file;
    }

    private function etapes(): DepotEtapes
    {
        if ($this->etapes instanceof \Closure) {
            $this->etapes = ($this->etapes)();
        }
        return $this->etapes ?? throw new \LogicException('Chronomètre inactif.');
    }

    private function file(): ?FileTraitement
    {
        if ($this->file instanceof \Closure) {
            $this->file = ($this->file)();
        }
        return $this->file;
    }

    /** Un chronomètre qui n'écrit rien : bancs, outils, tests qui ne le regardent pas. */
    public static function inactif(): self
    {
        return new self(null, null, new NativeClock('UTC'), new NullLogger());
    }

    public function horloge(): ClockInterface
    {
        return $this->horloge;
    }

    /**
     * Le passage commence (le verrou vient d'être pris, ou un rejeu démarre). Écrit l'attente avant le traitement, ou
     * la reprise après un passage mort.
     */
    public function ouvrirPassage(int $enregistrementId, ?string $uuid, ?string $jeton, int $tentative, string $processus, ?int $rejeuId = null): void
    {
        if ($this->etapes === null) {
            return;
        }
        $this->passage = ['enregistrement' => $enregistrementId, 'rejeu' => $rejeuId, 'uuid' => $uuid, 'jeton' => $jeton,
            'tentative' => $tentative, 'processus' => $processus];
        $this->journalise = false;
        $maintenant = $this->horloge->now();
        $this->dernierPouls = $maintenant;
        $this->noter(function () use ($enregistrementId, $rejeuId, $tentative, $processus, $maintenant): void {
            $etapes = $this->etapes();
            $abandonnees = $etapes->abandonnees($enregistrementId, $rejeuId);
            if ($abandonnees !== []) {
                $etapes->interrompre(array_column($abandonnees, 'id'));
                $etapes->ajouter($enregistrementId, $rejeuId, $tentative, 'reprise', $processus,
                    DepotEtapes::depuisBase($abandonnees[0]['debut']), $maintenant);
                return;
            }
            if ($rejeuId !== null) {
                return;
            }
            $derniere = $etapes->derniere($enregistrementId, null);
            $depuis = match (true) {
                $derniere === null => $etapes->recuLe($enregistrementId),
                $derniere['issue'] === DepotEtapes::ERREUR && $derniere['fin'] !== null => DepotEtapes::depuisBase($derniere['fin']),
                // Seulement la réception ou l'envoi : le traitement n'a pas encore commencé.
                default => $etapes->seulementReception($enregistrementId) ? ($etapes->finReception($enregistrementId) ?? $etapes->recuLe($enregistrementId)) : null,
            };
            if ($depuis !== null) {
                $etapes->ajouter($enregistrementId, null, $tentative, 'attente', $processus, $depuis, $maintenant);
            }
        });
    }

    public function fermerPassage(): void
    {
        $this->passage = null;
        $this->dernierPouls = null;
        $this->ouvertes = [];
    }

    /**
     * Le passage s'arrête sur une exception : ses étapes encore ouvertes passent en erreur, avec le message. Sans cela,
     * le passage suivant les prendrait pour celles d'un traitement mort. L'attente de la transcription reste ouverte.
     */
    public function echouerOuvertes(\Throwable $e): void
    {
        foreach ($this->ouvertes as $id => $o) {
            if ($o['etape'] !== 'transcription') {
                $this->terminer($id, $o['debut'], DepotEtapes::ERREUR, self::resume($e), null, null, null, null);
            }
        }
    }

    public function commencer(string $etape, ?string $modele = null): EtapeChrono
    {
        $p = $this->passage;
        if ($this->etapes === null || $p === null) {
            return EtapeChrono::rien();
        }
        $this->pouls();
        $debut = $this->horloge->now();
        $id = $this->noter(fn (): int => $this->etapes()
            ->ouvrir($p['enregistrement'], $p['rejeu'], $p['tentative'], $etape, $p['processus'], $debut, $modele));
        if ($id === null) {
            return EtapeChrono::rien();
        }
        $this->ouvertes[$id] = ['etape' => $etape, 'debut' => $debut];
        return new EtapeChrono($this, $id, $debut);
    }

    /**
     * @template T
     * @param callable(): T $f
     * @return T
     */
    public function mesurer(string $etape, callable $f): mixed
    {
        $e = $this->commencer($etape);
        try {
            $resultat = $f();
        } catch (\Throwable $x) {
            $e->echouer($x);
            throw $x;
        }
        $e->finir();
        return $resultat;
    }

    /** Ferme l'étape de ce nom laissée ouverte par un passage précédent (l'attente de la transcription). */
    public function finirOuverte(string $etape, ?\Throwable $erreur = null): void
    {
        if ($this->etapes === null || $this->passage === null) {
            return;
        }
        $p = $this->passage;
        $this->noter(function () use ($p, $etape, $erreur): void {
            $etapes = $this->etapes();
            $o = $etapes->ouverte($p['enregistrement'], $p['rejeu'], $etape);
            if ($o !== null) {
                $etapes->terminer($o['id'], DepotEtapes::depuisBase($o['debut']), $this->horloge->now(),
                    $erreur === null ? DepotEtapes::OK : DepotEtapes::ERREUR, $erreur === null ? null : self::resume($erreur), null, null, null, null);
            }
        });
    }

    /**
     * Prolonge le verrou du passage, au plus une fois toutes les POULS_SECONDES. Appelé par on_progress pendant un appel
     * HTTP : l'exception VerrouPerdu coupe l'appel (Symfony l'enveloppe dans une TransportException, getPrevious()).
     */
    public function pouls(): void
    {
        $p = $this->passage;
        $file = $this->file();
        if ($p === null || $p['uuid'] === null || $p['jeton'] === null || $file === null) {
            return;
        }
        $maintenant = $this->horloge->now();
        if ($this->dernierPouls !== null && $maintenant < $this->dernierPouls->modify('+' . self::POULS_SECONDES . ' seconds')) {
            return;
        }
        try {
            $tenu = $file->prolonger($p['uuid'], $p['jeton'], $maintenant->modify('+' . self::BAIL_SECONDES . ' seconds'));
        } catch (\Throwable $e) {
            // Base injoignable un instant : l'appel en cours continue, le prochain pouls réessaiera.
            $this->journal->warning('offreGen, pouls du verrou : ' . $e::class);
            return;
        }
        if (!$tenu) {
            throw new VerrouPerdu('Verrou perdu : un autre traitement a repris cet enregistrement.');
        }
        $this->dernierPouls = $maintenant;
    }

    /** L'exception vient-elle d'un verrou repris, directement ou enveloppée par le client HTTP ? */
    public static function verrouPerdu(\Throwable $e): bool
    {
        for ($x = $e; $x !== null; $x = $x->getPrevious()) {
            if ($x instanceof VerrouPerdu) {
                return true;
            }
        }
        return false;
    }

    /** @internal appelé par EtapeChrono */
    public function terminer(int $id, \DateTimeImmutable $debut, string $issue, ?string $erreur, ?string $modele, ?int $envoyes, ?int $recus, ?int $premierOctet): void
    {
        unset($this->ouvertes[$id]);
        $fin = $this->horloge->now();
        $this->noter(fn () => $this->etapes()
            ->terminer($id, $debut, $fin, $issue, $erreur, $modele, $envoyes, $recus, $premierOctet));
    }

    public static function resume(\Throwable $e): string
    {
        $classe = substr(strrchr('\\' . $e::class, '\\') ?: $e::class, 1);
        return mb_substr($classe . ' : ' . $e->getMessage(), 0, 190);
    }

    /**
     * @template T
     * @param callable(): T $f
     * @return T|null
     */
    private function noter(callable $f): mixed
    {
        try {
            return $f();
        } catch (\Throwable $e) {
            if (!$this->journalise) {
                $this->journal->warning('offreGen, chronomètre : écriture impossible (' . $e::class . ' : ' . mb_substr($e->getMessage(), 0, 120) . ').');
                $this->journalise = true;
            }
            return null;
        }
    }
}
