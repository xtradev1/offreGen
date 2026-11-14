<?php

declare(strict_types=1);

namespace OffreGen\Audio;

use OffreGen\Ia\ErreurApi;
use OffreGen\Ia\ReponseApi;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Transcription par le protocole d'Infomaniak (documentation lue le 2026-11-12) :
 * POST /1/ai/{produit}/openai/audio/transcriptions rend un batch_id ;
 * GET /1/ai/{produit}/results/{batch_id} rend le statut, puis le texte dans « data ».
 * En local, outils/ia-locale/whisper_serveur.py parle le même protocole : seuls les réglages changent.
 */
final class TranscripteurApi implements Transcripteur
{
    /** Code d'erreur d'Infomaniak pour un lot qui n'existe pas (ou plus) sous ce produit. */
    public const LOT_INCONNU = 'access_result_forbidden';

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly PreparationAudio $preparation,
        private readonly string $url,
        private readonly string $produit,
        private readonly string $cle,
        private readonly string $modele,
    ) {
        if (preg_match('/^\d+$/', $produit) !== 1) {
            throw new \InvalidArgumentException('TRANSCRIPTION_PRODUIT doit être un nombre.');
        }
    }

    public function demarrer(string $cheminAudio): string
    {
        $fichier = $this->preparation->preparer($cheminAudio);
        try {
            $formulaire = new FormDataPart([
                'model' => $this->modele,
                'language' => 'fr',
                'response_format' => 'text',
                'file' => DataPart::fromPath($fichier->chemin, 'audio.' . strtolower(pathinfo($fichier->chemin, PATHINFO_EXTENSION))),
            ]);
            // Longueur annoncée : sans elle, l'envoi part par morceaux, qu'un service peut refuser (411).
            // Elle se calcule en lisant le flux une fois, sans charger le fichier en mémoire.
            $longueur = 0;
            foreach ($formulaire->bodyToIterable() as $morceau) {
                $longueur += strlen($morceau);
            }
            $reponse = $this->http->request('POST', $this->base() . '/openai/audio/transcriptions', [
                'headers' => $this->entetes() + $formulaire->getPreparedHeaders()->toArray() + ['Content-Length' => (string) $longueur],
                'body' => $formulaire->bodyToIterable(),
                'timeout' => 300,
            ]);
            $donnees = ReponseApi::lire($reponse, 'Envoi à la transcription');
        } finally {
            $fichier->nettoyer();
        }
        $lot = $donnees['batch_id'] ?? null;
        if (!is_string($lot) || preg_match('/^[A-Za-z0-9-]{1,100}$/', $lot) !== 1) {
            throw new \RuntimeException('Envoi à la transcription : aucun numéro de lot dans la réponse.');
        }
        return $lot;
    }

    public function resultat(string $reference): ?string
    {
        if (preg_match('/^[A-Za-z0-9-]{1,100}$/', $reference) !== 1) {
            throw new TranscriptionEchouee('Référence de transcription invalide.');
        }
        try {
            $reponse = $this->http->request('GET', $this->base() . '/results/' . $reference, [
                'headers' => $this->entetes(),
                'timeout' => 60,
            ]);
            $donnees = ReponseApi::lire($reponse, 'Résultat de la transcription');
        } catch (ErreurApi $e) {
            // Lot inconnu : 404 pour le serveur local, 403 access_result_forbidden chez Infomaniak (relevé le 2026-11-14).
            if ($e->getCode() === 404 || ($e->getCode() === 403 && $e->codeService === self::LOT_INCONNU)) {
                throw new TranscriptionEchouee('Lot de transcription introuvable chez le prestataire.');
            }
            throw $e;
        }
        return match ($donnees['status'] ?? null) {
            'pending', 'processing' => null,
            'success' => self::texte($donnees['data'] ?? ''),
            'failed', 'cancelled' => throw new TranscriptionEchouee('Transcription ' . $donnees['status'] . ' chez le prestataire.'),
            default => throw new \RuntimeException('Résultat de la transcription : statut inconnu.'),
        };
    }

    /** « data » contient le texte (response_format=text) ; un JSON {"text": …} est accepté aussi. */
    private static function texte(mixed $data): string
    {
        if (is_array($data) && is_string($data['text'] ?? null)) {
            return trim($data['text']);
        }
        if (!is_string($data)) {
            throw new \RuntimeException('Résultat de la transcription : texte illisible.');
        }
        $json = json_decode($data, true);
        if (is_array($json) && is_string($json['text'] ?? null)) {
            return trim($json['text']);
        }
        return trim($data);
    }

    private function base(): string
    {
        return rtrim($this->url, '/') . '/1/ai/' . $this->produit;
    }

    /** @return array<string, string> */
    private function entetes(): array
    {
        return $this->cle === '' ? [] : ['Authorization' => 'Bearer ' . $this->cle];
    }
}
