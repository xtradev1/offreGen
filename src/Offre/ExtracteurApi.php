<?php

declare(strict_types=1);

namespace OffreGen\Offre;

use OffreGen\Compte\Activite;
use OffreGen\Ia\ReponseApi;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Extraction par un modèle de langage, route « chat/completions » au format OpenAI : Ollama en local,
 * Infomaniak (POST /2/ai/{produit}/openai/v1/chat/completions) en production. L'outil « proposer_offre »
 * est imposé. Le modèle choisit les produits et les quantités ; le code reprend désignation, unité et prix
 * dans le catalogue, et ne croit jamais un prix du modèle pour un produit du catalogue.
 */
final class ExtracteurApi implements Extracteur
{
    /** Banc du 2026-11-17 : 300 postes dictés en 30 minutes (un toutes les 6 s), 96 % justes. Au-delà, coupé. */
    public const LIGNES_MAX = 300;

    /**
     * Jetons de sortie au plus (revue du 2026-11-18 : sans borne, la limite par défaut du service coupait l'offre
     * sans prévenir). Une ligne en coûte environ 57 (banc du 2026-11-17) : 300 lignes tiennent avec de la marge.
     */
    public const JETONS_SORTIE_MAX = 24000;

    private const JETONS_SORTIE_PIECES = 8000;

    private const CONSIGNE = <<<'TXT'
        Tu prépares le brouillon d'une offre pour un artisan suisse, à partir de la dictée de sa visite chez le client.
        Règles :
        - Une ligne par travail dicté. N'invente aucun travail qui n'est pas dicté.
        - Quand le travail correspond à un produit du catalogue (désignation ou synonymes), donne son identifiant et une quantité dans l'unité du catalogue.
        - Quand il n'y est pas, produit_id vaut null : écris la désignation et l'unité.
        - Pour chaque ligne, catalogue compris, relève dans prix_unitaire le prix unitaire dicté (« 45 m² à 15 francs » : quantité 45, unité m², prix 15). Si aucun prix n'est dit, ne mets pas prix_unitaire.
        - Un prix donné seul juste après un poste (« en bloc 500 francs », « bloc 500 francs », « le tout 500 francs ») est le prix forfaitaire de ce poste : ne crée pas de ligne à part.
        - Une précision sur l'ordre ou l'état des travaux (« après remise en état du plafond ») n'est pas un poste, sauf si une quantité ou un prix lui est donné.
        - Chaque travail ou frais dicté devient une ligne, même sans quantité ni prix : quantité 1 et unité « forfait » quand aucune quantité n'est dictée.
        - Une option ou une variante dite (« en option », « variante », « ou alors », « à la place de ») est une ligne à part. Une option prend son produit du catalogue comme toute ligne. Une variante garde sa propre désignation et son propre prix : ne lui donne pas le produit de la ligne qu'elle remplacerait et ne reporte pas son prix sur cette ligne.
        - certitude « a_verifier » dès que la quantité est approximative (environ, à peu près, une douzaine), déduite ou mal comprise ; sinon « sure ».
        - extrait : les mots exacts de la dictée d'où vient la ligne.
        - titre : court, la nature des travaux et le lieu, sans prix.
        - client : le nom du client tel qu'il est dicté (par exemple « Madame Exemple »), sinon une chaîne vide. lieu : l'adresse ou la localité dictée, sinon une chaîne vide. N'invente ni nom ni adresse.
        - Jamais de tiret long : une virgule à la place.
        TXT;

    /**
     * Second appel (sous-chantier 28) : ranger les lignes trouvées dans leurs pièces. À part, parce que demander la pièce
     * dans l'appel principal faisait perdre des prix dictés sur la dictée de Luigi (2026-11-17 : 4 à 6 lignes justes sur
     * 7, contre 6 à 7 sans) ; ici, l'extraction reste celle qui a été mesurée.
     */
    private const CONSIGNE_PIECES = <<<'TXT'
        Tu ranges les lignes d'une offre d'artisan dans les pièces où se font les travaux, d'après la dictée de sa visite.
        Règles :
        - L'artisan dicte pièce par pièce : une pièce annoncée vaut pour les postes qui suivent, jusqu'à la suivante.
        - Pour chaque ligne, donne son numéro et la pièce telle qu'elle est dite (avec l'étage s'il est dit).
        - « général » pour une ligne qui vaut pour tout le chantier, ou quand aucune pièce n'est dite.
        - Jamais de tiret long : une virgule à la place.
        TXT;

    /**
     * Les phrases de CONSIGNE qui changent avec l'activité du compte (décision de Xavier du 2026-11-22) : qui dicte et
     * dans quelle situation, et de quoi est fait le titre. Tout le reste est commun. Le Bâtiment garde CONSIGNE au
     * caractère près : c'est le texte mesuré au banc (tests/Offre/ExtracteurActiviteTest.php en garde l'empreinte).
     * Jamais mesuré hors bâtiment : cinq dictées par activité sont prévues à la recette du chantier.
     */
    private const PHRASES = [
        'exterieur' => [
            'pour un artisan suisse, à partir de la dictée de sa visite chez le client.' => 'pour un jardinier ou un paysagiste suisse, à partir de la dictée de sa visite chez le client.',
        ],
        'prestations' => [
            'pour un artisan suisse, à partir de la dictée de sa visite chez le client.' => "pour un prestataire de services suisse (informatique, conseil, marketing), à partir de ce qu'il dicte après son échange avec le client.",
            '- titre : court, la nature des travaux et le lieu, sans prix.' => '- titre : court, la nature de la prestation, sans prix.',
        ],
    ];

    /**
     * Troisième appel (options et variantes, chantier 2026-11-22) : dire quelles lignes trouvées sont des options ou des
     * variantes, à part de l'appel principal (leçon du sous-chantier 28). L'appel principal a seulement reçu une règle :
     * garder une option ou une variante sur sa propre ligne, mesurée au banc du 2026-11-22. Lancé seulement quand la
     * dictée contient un mot déclencheur (MOTS_OPTIONS) : une offre ordinaire ne coûte rien de plus.
     */
    private const CONSIGNE_OPTIONS = <<<'TXT'
        Tu relis les lignes d'une offre d'artisan, tirées de la dictée de sa visite, et tu dis lesquelles sont des options ou des variantes.
        Règles :
        - Une option est un travail en plus que le client peut prendre ou laisser : « en option », « option », « si le client veut », « éventuellement », « à voir avec le client ».
        - Une variante est une autre façon de faire un travail qui est déjà une ligne de l'offre, à sa place : « variante », « en variante », « ou alors », « à la place de ». Donne le numéro de la ligne qu'elle remplace.
        - Une variante dont le travail remplacé n'est pas une ligne de l'offre est une option.
        - Une quantité approximative (« peut-être 20 ») ou le mot option dans un autre sens (« elle a l'option de nous laisser la clé ») ne font pas une option.
        - Une ligne n'est une option ou une variante que si la dictée le dit pour ce travail-là. La plupart des offres n'en ont aucune : dans le doute, ne cite pas la ligne ; une liste vide est une réponse normale.
        - Ne cite que les options et les variantes ; toutes les autres lignes restent dans le total.
        - Jamais de tiret long : une virgule à la place.
        TXT;

    /** Les mots qui déclenchent le troisième appel ; leur absence le saute (mesuré au banc, décision fragile n° 2 du plan). */
    public const MOTS_OPTIONS = '/(*UCP)\b(options?|variantes?|ou alors|à la place|au choix|éventuel(?:le)?(?:ment)?|à voir avec|si (?:le|la|les) clients?)\b/iu';

    /** Le second appel de l'Extérieur : la zone du terrain à la place de la pièce. Jamais mesuré au banc. */
    private const CONSIGNE_ZONES = <<<'TXT'
        Tu ranges les lignes d'une offre de jardinier ou de paysagiste dans les zones du terrain où se font les travaux, d'après la dictée de sa visite.
        Règles :
        - Il dicte zone par zone (« devant la maison », « la haie côté route », « le talus ») : une zone annoncée vaut pour les postes qui suivent, jusqu'à la suivante.
        - Pour chaque ligne, donne son numéro et la zone telle qu'elle est dite.
        - « général » pour une ligne qui vaut pour tout le chantier, ou quand aucune zone n'est dite.
        - Jamais de tiret long : une virgule à la place.
        TXT;

    /** La consigne de l'appel principal, pour l'activité du compte. */
    public static function consigne(Activite $activite): string
    {
        return strtr(self::CONSIGNE, self::PHRASES[$activite->value] ?? []);
    }

    /** La consigne du second appel : par pièce, ou par zone en Extérieur. Les Prestations n'ont pas de second appel. */
    public static function consigneGroupes(Activite $activite): string
    {
        return $activite === Activite::Exterieur ? self::CONSIGNE_ZONES : self::CONSIGNE_PIECES;
    }

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly string $url,
        private readonly string $cle,
        private readonly string $modele,
        private readonly LoggerInterface $journal,
        private readonly bool $sansRaisonnement = true,
        /** Modèle du second appel (pièces) ; null : le même que l'extraction. */
        private readonly ?string $modelePieces = null,
        /** Troisième appel des options et variantes ; faux : le banc mesure l'extraction sans lui. */
        private readonly bool $classerOptions = true,
    ) {
    }

    public function extraire(string $transcription, array $catalogue, Activite $activite = Activite::Batiment): ResultatExtraction
    {
        if (trim($transcription) === '') {
            return new ResultatExtraction(null, []);
        }
        $corps = [
            'model' => $this->modele,
            'temperature' => 0,
            'messages' => [
                ['role' => 'system', 'content' => self::consigne($activite)],
                ['role' => 'user', 'content' => "Catalogue :\n" . self::catalogueEnTexte($catalogue) . "\n\nDictée :\n" . $transcription],
            ],
            'tools' => [self::outil()],
            'tool_choice' => ['type' => 'function', 'function' => ['name' => 'proposer_offre']],
            'max_tokens' => self::JETONS_SORTIE_MAX,
        ];
        if ($this->sansRaisonnement) {
            $corps['reasoning_effort'] = 'none';
        }
        $reponse = $this->http->request('POST', rtrim($this->url, '/') . '/chat/completions', [
            'headers' => $this->cle === '' ? [] : ['Authorization' => 'Bearer ' . $this->cle],
            'json' => $corps,
            'timeout' => 300,
            // Durée totale bornée, sous le verrou de 900 s du traitement (revue du 2026-11-18, B2).
            'max_duration' => 600,
        ]);
        $donnees = ReponseApi::lire($reponse, 'Extraction');
        $this->noterJetons($donnees, 'extraction', $this->modele);
        $proposition = self::argumentsDeLOutil($donnees, 'proposer_offre');
        if ($proposition === null) {
            // Règle de Xavier (2026-11-12) : rien d'utile dit, offre vide. Avec une température à 0, réessayer
            // donnait cinq fois la même réponse, puis un échec.
            $this->journal->warning('offreGen, extraction : le modèle n\'a proposé aucune ligne, offre vide.');
            return new ResultatExtraction(null, []);
        }
        $resultat = $this->valider($proposition, $catalogue);
        // Une prestation ne se range ni par pièce ni par zone : l'offre sort par catégorie du catalogue, sans second appel.
        if ($resultat->lignes !== [] && $activite->rangeParGroupe()) {
            $resultat = $this->rangerParPiece($transcription, $resultat, $activite);
        }
        return $resultat->lignes !== [] && $this->classerOptions && self::aDesMotsDOption($transcription) ? $this->classerOptions($transcription, $resultat) : $resultat;
    }

    /** La dictée parle peut-être d'options ou de variantes : le troisième appel vaut la peine. */
    public static function aDesMotsDOption(string $transcription): bool
    {
        return preg_match(self::MOTS_OPTIONS, $transcription) === 1;
    }

    /**
     * Les options et variantes parmi les lignes trouvées, par un troisième appel sans catalogue. Un échec ne coûte que
     * cette information : l'offre sort avec toutes ses lignes dans le total, comme avant.
     */
    private function classerOptions(string $transcription, ResultatExtraction $resultat): ResultatExtraction
    {
        $liste = [];
        foreach ($resultat->lignes as $i => $l) {
            $liste[] = ($i + 1) . '. ' . $l->designation . ($l->entendu === null ? '' : ' (« ' . $l->entendu . ' »)');
        }
        $corps = [
            'model' => $this->modelePieces ?? $this->modele,
            'temperature' => 0,
            'messages' => [
                ['role' => 'system', 'content' => self::CONSIGNE_OPTIONS],
                ['role' => 'user', 'content' => "Dictée :\n" . $transcription . "\n\nLignes :\n" . implode("\n", $liste)],
            ],
            'tools' => [self::outilOptions()],
            'tool_choice' => ['type' => 'function', 'function' => ['name' => 'classer_lignes']],
            'max_tokens' => self::JETONS_SORTIE_PIECES,
        ];
        if ($this->sansRaisonnement) {
            $corps['reasoning_effort'] = 'none';
        }
        try {
            // Durée bornée : avec l'appel principal (600 s) et les pièces (180 s), 870 s, sous le verrou de 900 s du
            // traitement (Audio\Traiteur ; revue du chantier des options, S1).
            $reponse = $this->http->request('POST', rtrim($this->url, '/') . '/chat/completions', [
                'headers' => $this->cle === '' ? [] : ['Authorization' => 'Bearer ' . $this->cle],
                'json' => $corps,
                'timeout' => 60,
                'max_duration' => 90,
            ]);
            $donnees = ReponseApi::lire($reponse, 'Options');
            $this->noterJetons($donnees, 'options', $this->modelePieces ?? $this->modele);
            $arguments = self::argumentsDeLOutil($donnees, 'classer_lignes') ?? [];
        } catch (\Throwable $e) {
            $this->journal->warning('offreGen, options : troisième appel en échec, toutes les lignes dans le total (' . $e::class . ').');
            return $resultat;
        }
        return new ResultatExtraction($resultat->titre, $this->validerOptions($arguments, $resultat->lignes), $resultat->client, $resultat->lieu, $resultat->postesCoupes);
    }

    /**
     * La sortie du troisième appel, traitée comme non fiable : un numéro hors de la liste ou une nature inconnue sont
     * ignorés ; une variante sans ligne de base valable devient une option (O3). Toute ligne que l'appel sort du total
     * passe à vérifier : une erreur du modèle ne fait pas disparaître d'argent en silence (revue, R1). Une variante
     * comptée 1 dans l'unité de sa ligne (quantité non dite) prend la quantité de sa ligne (O2) ; une variante au
     * forfait garde la sienne (« 800 francs le tout », revue, C2). Elle prend la pièce de sa ligne.
     *
     * @param array<string, mixed> $arguments
     * @param list<LigneExtraite> $lignes
     * @return list<LigneExtraite>
     */
    public function validerOptions(array $arguments, array $lignes): array
    {
        $choix = [];
        foreach (is_array($arguments['lignes'] ?? null) ? $arguments['lignes'] : [] as $x) {
            $i = is_array($x) && is_int($x['ligne'] ?? null) ? $x['ligne'] - 1 : -1;
            $nature = is_array($x) ? ($x['nature'] ?? null) : null;
            if (!isset($lignes[$i]) || !in_array($nature, [Options::OPTION, Options::VARIANTE], true) || isset($choix[$i])) {
                continue;
            }
            $base = is_int($x['remplace'] ?? null) ? $x['remplace'] - 1 : null;
            $choix[$i] = [$nature, $base];
        }
        $sortie = [];
        foreach ($lignes as $i => $l) {
            [$nature, $base] = $choix[$i] ?? [Options::LIGNE, null];
            $doute = $l->aVerifier || $nature !== Options::LIGNE;
            $quantite = $l->quantiteMilliemes;
            $unite = $l->unite;
            $piece = $l->piece;
            if ($nature === Options::VARIANTE) {
                // La ligne remplacée doit être une ligne du total de la liste, une autre que la variante.
                if ($base === null || $base === $i || !isset($lignes[$base]) || isset($choix[$base])) {
                    $this->journal->info("offreGen, options : variante $i sans ligne de base valable, gardée en option à vérifier.");
                    $nature = Options::OPTION;
                    $base = null;
                } else {
                    $b = $lignes[$base];
                    if ($quantite === 1000 && $unite !== 'forfait' && $unite === $b->unite) {
                        $quantite = $b->quantiteMilliemes;
                    }
                    $piece = $b->piece;
                }
            }
            $sortie[] = new LigneExtraite($l->designation, $quantite, $unite, $l->prixCentimes, $doute, $l->produitId, $l->entendu, $l->prixDicteCentimes,
                $piece, $nature, $nature === Options::VARIANTE ? $base : null);
        }
        return $sortie;
    }

    /**
     * Range les lignes dans leurs pièces (leurs zones, en Extérieur) par un second appel, sans le catalogue (de quelques
     * dixièmes de centime). Un échec ne coûte que les pièces : l'offre sort comme avant, rangée par catégorie.
     */
    private function rangerParPiece(string $transcription, ResultatExtraction $resultat, Activite $activite): ResultatExtraction
    {
        $liste = [];
        foreach ($resultat->lignes as $i => $l) {
            $liste[] = ($i + 1) . '. ' . $l->designation . ($l->entendu === null ? '' : ' (« ' . $l->entendu . ' »)');
        }
        $corps = [
            'model' => $this->modelePieces ?? $this->modele,
            'temperature' => 0,
            'messages' => [
                ['role' => 'system', 'content' => self::consigneGroupes($activite)],
                ['role' => 'user', 'content' => "Dictée :\n" . $transcription . "\n\nLignes :\n" . implode("\n", $liste)],
            ],
            'tools' => [self::outilPieces($activite)],
            'tool_choice' => ['type' => 'function', 'function' => ['name' => 'ranger_par_piece']],
            'max_tokens' => self::JETONS_SORTIE_PIECES,
        ];
        if ($this->sansRaisonnement) {
            $corps['reasoning_effort'] = 'none';
        }
        try {
            $reponse = $this->http->request('POST', rtrim($this->url, '/') . '/chat/completions', [
                'headers' => $this->cle === '' ? [] : ['Authorization' => 'Bearer ' . $this->cle],
                'json' => $corps,
                'timeout' => 120,
                'max_duration' => 180,
            ]);
            $donnees = ReponseApi::lire($reponse, 'Pièces');
            $this->noterJetons($donnees, 'pièces', $this->modelePieces ?? $this->modele);
            $arguments = self::argumentsDeLOutil($donnees, 'ranger_par_piece');
        } catch (\Throwable $e) {
            $this->journal->warning('offreGen, pièces : second appel en échec, offre sans pièces (' . $e::class . ').');
            return $resultat;
        }
        $pieces = [];
        foreach (is_array($arguments['pieces'] ?? null) ? $arguments['pieces'] : [] as $p) {
            if (is_array($p) && is_int($p['ligne'] ?? null)) {
                $pieces[$p['ligne'] - 1] = Piece::lire($p['piece'] ?? null);
            }
        }
        $lignes = [];
        foreach ($resultat->lignes as $i => $l) {
            $lignes[] = new LigneExtraite($l->designation, $l->quantiteMilliemes, $l->unite, $l->prixCentimes, $l->aVerifier, $l->produitId,
                $l->entendu, $l->prixDicteCentimes, $pieces[$i] ?? null, $l->nature, $l->remplace);
        }
        return new ResultatExtraction($resultat->titre, $lignes, $resultat->client, $resultat->lieu, $resultat->postesCoupes);
    }

    /**
     * Jetons consommés, tels que le service les déclare : de quoi chiffrer le coût d'une offre.
     * Seulement des nombres, jamais le texte de la dictée. Rien si le service ne les donne pas.
     *
     * @param array<string, mixed> $donnees
     */
    private function noterJetons(array $donnees, string $appel, string $modele): void
    {
        $usage = $donnees['usage'] ?? null;
        if (!is_array($usage) || !is_int($usage['prompt_tokens'] ?? null) || !is_int($usage['completion_tokens'] ?? null)) {
            return;
        }
        $this->journal->info(sprintf('offreGen, %s : %d jetons envoyés, %d reçus, modèle %s.', $appel, $usage['prompt_tokens'], $usage['completion_tokens'], $modele));
    }

    /**
     * @param array<string, mixed> $proposition
     * @param list<array{id: int, categorie: string, designation: string, unite: string, prix_centimes: int, synonymes: string|null}> $catalogue
     */
    public function valider(array $proposition, array $catalogue): ResultatExtraction
    {
        $parId = array_column($catalogue, null, 'id');
        $lignes = [];
        $brutes = is_array($proposition['lignes'] ?? null) ? array_values($proposition['lignes']) : [];
        $coupes = max(0, count($brutes) - self::LIGNES_MAX);
        if ($coupes > 0) {
            $this->journal->warning("offreGen, extraction : $coupes poste(s) au-delà de " . self::LIGNES_MAX . " lignes, écartés ; l'artisan est averti.");
        }
        foreach (array_slice($brutes, 0, self::LIGNES_MAX) as $rang => $brute) {
            if (!is_array($brute)) {
                continue;
            }
            $quantite = $brute['quantite'] ?? null;
            $milliemes = (is_int($quantite) || is_float($quantite)) && $quantite > 0 && $quantite * 1000 <= Saisie::QUANTITE_MAX_MILLIEMES
                ? (int) round($quantite * 1000) : 0;
            $devinee = $milliemes <= 0;
            if ($devinee) {
                // Un poste dicté ne se perd jamais pour sa quantité (essai de Xavier du 2026-11-16 : « des frais de
                // nettoyage », rendus par Qwen 27B avec une quantité null, avaient disparu de l'offre) : 1, à vérifier.
                $this->journal->info("offreGen, extraction : ligne $rang sans quantité valable, gardée à 1, à vérifier.");
                $milliemes = 1000;
            }
            $entendu = is_string($brute['extrait'] ?? null) ? mb_substr(self::texte($brute['extrait']), 0, 500) : null;
            $doute = $devinee || ($brute['certitude'] ?? '') !== 'sure';
            $id = $brute['produit_id'] ?? null;
            // Qwen rend parfois l'identifiant en texte (« "12" ») : c'est le même produit.
            $id = is_string($id) && ctype_digit($id) ? (int) $id : $id;
            $piece = Piece::lire($brute['piece'] ?? null);
            $dicte = $brute['prix_unitaire'] ?? null;
            // Un prix dicté hors des bornes de la saisie à l'écran est un mot mal compris : ignoré.
            $dicteCentimes = (is_int($dicte) || is_float($dicte)) && $dicte > 0 && $dicte * 100 <= Saisie::PRIX_MAX_CENTIMES
                ? (int) round($dicte * 100) : null;
            if (is_int($id) && isset($parId[$id])) {
                $p = $parId[$id];
                // Le prix du catalogue reste celui de la ligne ; un autre prix dicté est gardé à côté et la ligne
                // passe à vérifier (Xavier, 2026-11-17 : « catalogue par défaut », une note « prix dicté »).
                $autre = $dicteCentimes !== null && $dicteCentimes !== $p['prix_centimes'] ? $dicteCentimes : null;
                $lignes[] = new LigneExtraite($p['designation'], $milliemes, $p['unite'], $p['prix_centimes'], $doute || $p['prix_centimes'] === 0 || $autre !== null, $p['id'], $entendu, $autre, $piece);
                continue;
            }
            // Ligne libre, ou produit inconnu du catalogue de cet artisan : toujours à vérifier.
            $designation = is_string($brute['designation'] ?? null) ? mb_substr(self::texte($brute['designation']), 0, 255) : '';
            if ($designation === '') {
                $this->journal->warning("offreGen, extraction : ligne $rang sans désignation ni produit connu, écartée.");
                continue;
            }
            $unite = is_string($brute['unite'] ?? null) && trim($brute['unite']) !== '' ? mb_substr(trim($brute['unite']), 0, 20) : 'forfait';
            $lignes[] = new LigneExtraite($designation, $milliemes, $unite, $dicteCentimes ?? 0, true, null, $entendu, null, $piece);
        }
        $titre = is_string($proposition['titre'] ?? null) ? mb_substr(self::texte($proposition['titre']), 0, 90) : '';
        $client = is_string($proposition['client'] ?? null) ? mb_substr(self::texte($proposition['client']), 0, 190) : '';
        $lieu = is_string($proposition['lieu'] ?? null) ? mb_substr(self::texte($proposition['lieu']), 0, 255) : '';
        return new ResultatExtraction($titre === '' ? null : $titre, $lignes, $client === '' ? null : $client, $lieu === '' ? null : $lieu, $coupes);
    }

    /**
     * Texte écrit par le modèle, qui finit sur une offre envoyée au client : sans tiret long
     * (règle de XtraDev ; Qwen en a produit un dans un titre le 2026-11-12).
     */
    public static function texte(string $brut): string
    {
        $t = preg_replace('/\s*[\x{2014}\x{2013}]\s*/u', ', ', trim($brut)) ?? '';
        return trim(preg_replace('/^,\s*|,\s*$/u', '', $t) ?? '');
    }

    /**
     * Les arguments de l'appel à proposer_offre ; null si le modèle a répondu sans l'appeler.
     *
     * @param array<string, mixed> $donnees
     * @return array<string, mixed>|null
     */
    private static function argumentsDeLOutil(array $donnees, string $nom): ?array
    {
        $choix = is_array($donnees['choices'] ?? null) ? $donnees['choices'] : [];
        $message = is_array($choix[0] ?? null) && is_array($choix[0]['message'] ?? null) ? $choix[0]['message'] : null;
        if ($message === null) {
            throw new \RuntimeException('Extraction : réponse sans message du modèle.');
        }
        // Coupée par la limite de jetons : l'offre serait incomplète ou vide. Un échec se réessaie, une offre vide se perd.
        if (($choix[0]['finish_reason'] ?? null) === 'length') {
            throw new \RuntimeException('Extraction : réponse coupée par la limite de jetons de sortie.');
        }
        $appels = is_array($message['tool_calls'] ?? null) ? $message['tool_calls'] : [];
        foreach ($appels as $appel) {
            $fonction = is_array($appel) && is_array($appel['function'] ?? null) ? $appel['function'] : [];
            if (($fonction['name'] ?? null) !== $nom) {
                continue;
            }
            $arguments = $fonction['arguments'] ?? null;
            $decode = is_string($arguments) ? json_decode($arguments, true) : $arguments;
            if (is_array($decode)) {
                /** @var array<string, mixed> $decode */
                return $decode;
            }
            throw new \RuntimeException("Extraction : arguments de $nom illisibles.");
        }
        // Le modèle a répondu sans remplir l'offre : la dictée ne contenait rien d'utile.
        return null;
    }

    /** @param list<array{id: int, categorie: string, designation: string, unite: string, prix_centimes: int, synonymes: string|null}> $catalogue */
    private static function catalogueEnTexte(array $catalogue): string
    {
        $lignes = ['id|catégorie|désignation|unité|prix CHF|synonymes'];
        foreach ($catalogue as $p) {
            $prix = $p['prix_centimes'] === 0 ? '?' : number_format($p['prix_centimes'] / 100, 2, '.', '');
            $lignes[] = implode('|', [$p['id'], $p['categorie'], $p['designation'], $p['unite'], $prix, (string) $p['synonymes']]);
        }
        return implode("\n", $lignes);
    }

    /**
     * Le nom de l'outil et de son champ restent ceux de la pièce : seuls les mots lus par le modèle suivent l'activité.
     *
     * @return array<string, mixed>
     */
    private static function outilPieces(Activite $activite): array
    {
        $zone = $activite === Activite::Exterieur;
        return ['type' => 'function', 'function' => [
            'name' => 'ranger_par_piece',
            'description' => $zone ? 'Donne la zone de chaque ligne de l\'offre.' : 'Donne la pièce de chaque ligne de l\'offre.',
            'parameters' => [
                'type' => 'object',
                'required' => ['pieces'],
                'properties' => [
                    'pieces' => ['type' => 'array', 'items' => [
                        'type' => 'object',
                        'required' => ['ligne', 'piece'],
                        'properties' => [
                            'ligne' => ['type' => 'integer', 'description' => 'Numéro de la ligne'],
                            'piece' => ['type' => 'string', 'description' => $zone ? 'Zone dite, ou « général »' : 'Pièce dite, ou « général »'],
                        ],
                    ]],
                ],
            ],
        ]];
    }

    /** @return array<string, mixed> */
    private static function outilOptions(): array
    {
        return ['type' => 'function', 'function' => [
            'name' => 'classer_lignes',
            'description' => 'Donne les lignes de l\'offre qui sont des options ou des variantes.',
            'parameters' => [
                'type' => 'object',
                'required' => ['lignes'],
                'properties' => [
                    'lignes' => ['type' => 'array', 'items' => [
                        'type' => 'object',
                        'required' => ['ligne', 'nature'],
                        'properties' => [
                            'ligne' => ['type' => 'integer', 'description' => 'Numéro de la ligne'],
                            'nature' => ['type' => 'string', 'enum' => ['option', 'variante']],
                            'remplace' => ['type' => ['integer', 'null'], 'description' => 'Pour une variante : numéro de la ligne qu\'elle remplace'],
                        ],
                    ]],
                ],
            ],
        ]];
    }

    /** @return array<string, mixed> */
    private static function outil(): array
    {
        return ['type' => 'function', 'function' => [
            'name' => 'proposer_offre',
            'description' => 'Propose le titre et les lignes de l\'offre tirées de la dictée.',
            'parameters' => [
                'type' => 'object',
                'required' => ['titre', 'lignes'],
                'properties' => [
                    'titre' => ['type' => 'string'],
                    'client' => ['type' => 'string', 'description' => 'Nom du client dicté, ou vide'],
                    'lieu' => ['type' => 'string', 'description' => 'Adresse ou localité dictée, ou vide'],
                    'lignes' => ['type' => 'array', 'items' => [
                        'type' => 'object',
                        'required' => ['produit_id', 'quantite', 'certitude', 'extrait'],
                        'properties' => [
                            'produit_id' => ['type' => ['integer', 'null'], 'description' => 'Identifiant du catalogue, ou null pour une ligne libre'],
                            'designation' => ['type' => 'string', 'description' => 'Seulement pour une ligne libre'],
                            'quantite' => ['type' => 'number'],
                            'unite' => ['type' => 'string', 'description' => 'Seulement pour une ligne libre'],
                            'prix_unitaire' => ['type' => 'number', 'description' => 'Prix unitaire dicté en CHF, pour toute ligne où un prix est dit'],
                            'certitude' => ['type' => 'string', 'enum' => ['sure', 'a_verifier']],
                            'extrait' => ['type' => 'string'],
                        ],
                    ]],
                ],
            ],
        ]];
    }
}
