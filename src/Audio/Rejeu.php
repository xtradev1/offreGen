<?php

declare(strict_types=1);

namespace OffreGen\Audio;

use OffreGen\Compte\Activite;
use OffreGen\Donnees\Base;
use OffreGen\Donnees\DepotArtisans;
use OffreGen\Donnees\DepotEnregistrements;
use OffreGen\Donnees\DepotEtapes;
use OffreGen\Donnees\DepotProduits;
use OffreGen\Donnees\Portee;
use OffreGen\Offre\Extracteur;
use OffreGen\Systeme\Chronometre;
use Psr\Log\LoggerInterface;

/**
 * Rejouer une dictée depuis son rapport (sous-chantier 31, D2 de Xavier : « pouvoir refaire pour voir où se situe le
 * problème »). Refait transcription et appels à l'IA sur l'audio gardé, avec le catalogue du compte au moment du rejeu,
 * chronométrés sous le numéro du rejeu. L'offre, les compteurs de l'artisan et les courriels ne sont jamais touchés.
 * Lancé après la réponse, sans cron : un rejeu qui meurt reste « en cours » et se relance au bout de 15 minutes.
 */
final class Rejeu
{
    public const ATTENTE_TRANSCRIPTION_SECONDES = 600;
    public const INTERVALLE_SECONDES = 3;
    /** Un rejeu plus ancien encore « en cours » est tenu pour mort : on peut en relancer un. */
    public const BLOCAGE_MINUTES = 15;

    public function __construct(
        private readonly Base $base,
        private readonly DepotEtapes $etapes,
        private readonly Transcripteur $transcripteur,
        private readonly Extracteur $extracteur,
        private readonly DepotArtisans $artisans,
        private readonly StockageAudio $stockage,
        private readonly Chronometre $chrono,
        private readonly LoggerInterface $journal,
    ) {
    }

    public function executer(int $rejeuId): void
    {
        $r = $this->etapes->rejeu($rejeuId);
        if ($r === null || $r['statut'] !== 'en_cours') {
            return;
        }
        $horloge = $this->chrono->horloge();
        $this->chrono->ouvrirPassage($r['enregistrement_id'], null, null, 1, Chronometre::REJEU, $rejeuId);
        try {
            if ($r['fichier'] === null) {
                throw new \RuntimeException('Audio supprimé : rejeu impossible.');
            }
            $chemins = array_map($this->stockage->chemin(...), [$r['fichier'], ...DepotEnregistrements::suites($r['fichiers_suite'])]);
            $produits = new DepotProduits($this->base, new Portee($r['artisan_id']));
            $vocabulaire = VocabulaireDictee::depuis($produits->designationsParUsage());
            $references = [];
            foreach ($chemins as $chemin) {
                $references[] = $this->transcripteur->demarrer($chemin, $vocabulaire);
            }
            $attente = $this->chrono->commencer('transcription');
            $limite = $horloge->now()->modify('+' . self::ATTENTE_TRANSCRIPTION_SECONDES . ' seconds');
            while (($texte = $this->textes($references)) === null) {
                if ($horloge->now() >= $limite) {
                    throw new TranscriptionEchouee('Transcription sans résultat après ' . self::ATTENTE_TRANSCRIPTION_SECONDES . ' s.');
                }
                $horloge->sleep(self::INTERVALLE_SECONDES);
            }
            $attente->finir();
            $activite = Activite::lire($this->artisans->parId($r['artisan_id'])['activite'] ?? null);
            $resultat = $this->extracteur->extraire($texte, $produits->tous(), $activite);
            $this->etapes->finirRejeu($rejeuId, 'fini', $horloge->now(), $texte, $resultat->titre, count($resultat->lignes), null);
        } catch (\Throwable $e) {
            $this->chrono->echouerOuvertes($e);
            $this->etapes->finirRejeu($rejeuId, 'echec', $horloge->now(), null, null, null, Chronometre::resume($e));
            $this->journal->warning("offreGen, rejeu $rejeuId : " . Chronometre::resume($e));
        } finally {
            $this->chrono->fermerPassage();
        }
    }

    /** @param list<string> $references */
    private function textes(array $references): ?string
    {
        $textes = [];
        foreach ($references as $reference) {
            $texte = $this->transcripteur->resultat($reference);
            if ($texte === null) {
                return null;
            }
            $textes[] = $texte;
        }
        return implode("\n", array_filter($textes, static fn (string $t): bool => $t !== ''));
    }
}
