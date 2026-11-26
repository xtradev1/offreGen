<?php

declare(strict_types=1);

namespace OffreGen\Catalogue;

use OffreGen\Ia\ReponseApi;
use OffreGen\Offre\ExtracteurApi;
use OffreGen\Offre\Saisie;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Lit une ancienne offre de l'artisan pour en tirer des candidats au catalogue (chantier 2026-11-20_import-catalogue).
 * Le navigateur a sorti le texte du PDF, ligne par ligne (public/js/texte-pdf.js) ; le modèle relève les lignes chiffrées,
 * une par ligne de texte « n | désignation | unité | prix », au format OpenAI (Ollama en local, Infomaniak en service).
 * Pas d'appel d'outil : chez Infomaniak, la sortie par l'outil s'arrêtait parfois après une ligne (banc du 2026-11-20 :
 * 7 passes sur 17 sur l'offre du carreleur, relance comprise) ; la sortie en texte a passé 10 fois sur 10, avec 40 %
 * de jetons reçus en moins.
 *
 * Le modèle n'est jamais cru sur un prix : chaque prix doit se relire dans le texte, près de la ligne citée, et ne pas
 * être le montant de la ligne (quantité fois prix). Ce qui échoue est refusé, avec sa raison.
 */
final class LecteurApi implements Lecteur
{
    /** Une offre de 8 pages fait environ 400 lignes (devis public du banc, 2026-11-20). */
    public const LIGNES_MAX = 1200;
    public const CARACTERES_MAX = 80000;
    private const LARGEUR_LIGNE_MAX = 300;
    private const JETONS_SORTIE_MAX = 16000;
    /** Le prix se cherche de la ligne d'avant à trois lignes après celle que cite le modèle. */
    private const FENETRE_AVANT = 1;
    private const FENETRE_APRES = 3;

    private const CONSIGNE = <<<'TXT'
        Tu lis une ancienne offre (un devis) d'un artisan, pour bâtir son catalogue de prix. Les lignes du texte sont numérotées. Une barre « | » sépare les cellules d'un tableau.
        Relève toutes les lignes de travail ou de fourniture qui portent un prix unitaire, une par une, du début à la fin de l'offre.
        Réponds seulement par ces lignes, une par ligne relevée, sans titre ni commentaire :
        n | désignation | unité | prix
        - n : le numéro de la ligne du texte où se lit le prix unitaire.
        - désignation : le libellé du travail tel qu'il est écrit, sans le numéro de position, sans la quantité, sans le nom du client. Si le libellé continue sur la ligne suivante, réunis-le. Ne recopie pas un descriptif technique de plusieurs lignes : garde le libellé. Jamais de barre « | » dans la désignation.
        - unité : l'unité telle qu'elle est écrite (m2, ml, pce, h, forfait). Sans unité écrite : forfait.
        - prix : le prix unitaire, jamais le montant de la ligne (quantité fois prix unitaire). Copie le nombre écrit, ne calcule rien.
        - Ne relève ni les totaux, ni les sous-totaux, ni les rabais, ni la TVA, ni les acomptes, ni les titres de chapitre, ni les conditions.
        - N'invente rien : une ligne sans prix n'est pas relevée.
        TXT;

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly string $url,
        private readonly string $cle,
        private readonly string $modele,
        private readonly LoggerInterface $journal,
        private readonly bool $sansRaisonnement = true,
    ) {
    }

    /** @param list<string> $texte les lignes du PDF, dans l'ordre de lecture */
    public function lire(array $texte): LectureOffre
    {
        $lignes = array_map(static fn (string $l): string => mb_substr(trim($l), 0, self::LARGEUR_LIGNE_MAX), $texte);
        if (count($lignes) > self::LIGNES_MAX || mb_strlen(implode('', $lignes)) > self::CARACTERES_MAX) {
            throw new \InvalidArgumentException('Offre trop longue pour être lue.');
        }
        $numerotees = [];
        foreach ($lignes as $i => $l) {
            if ($l !== '') {
                $numerotees[] = ($i + 1) . ': ' . $l;
            }
        }
        if ($numerotees === []) {
            return new LectureOffre([]);
        }
        [$gardees, $refusees, $envoyes, $recus] = $this->appel("Offre :\n" . implode("\n", $numerotees), $lignes);
        $attendues = self::lignesDeTableau($lignes);
        if ($attendues >= 3 && count($gardees) < (int) ceil($attendues * 0.6)) {
            // Banc du 2026-11-20 chez Infomaniak : une passe sur quatre s'arrête après une ligne. Une seule relance,
            // consigne appuyée ; la lecture qui garde le plus de lignes l'emporte.
            $this->journal->warning(sprintf('offreGen, lecture d\'offre : %d lignes gardées pour environ %d dans le tableau, seconde lecture.', count($gardees), $attendues));
            [$g2, $r2, $e2, $c2] = $this->appel(
                "Relève toutes les lignes chiffrées : le tableau en compte environ $attendues lignes.\n\nOffre :\n" . implode("\n", $numerotees),
                $lignes,
            );
            $envoyes = $envoyes === null || $e2 === null ? null : $envoyes + $e2;
            $recus = $recus === null || $c2 === null ? null : $recus + $c2;
            if (count($g2) > count($gardees)) {
                [$gardees, $refusees] = [$g2, $r2];
            }
        }
        return new LectureOffre($gardees, $refusees, $envoyes, $recus);
    }

    /**
     * Le nombre de lignes du texte qui ressemblent à une ligne chiffrée d'un tableau : deux cellules ou plus faites
     * d'un nombre seul. Une offre sans tableau (une liste « 35 ml à CHF 14.- ») compte zéro et n'est jamais relue.
     *
     * @param list<string> $texte
     */
    public static function lignesDeTableau(array $texte): int
    {
        $n = 0;
        foreach ($texte as $ligne) {
            $chiffrees = array_filter(explode('|', $ligne), static fn (string $c): bool => preg_match('/^\s*[\d\'’ .,\x{00A0}\x{202F}-]*\d[\d\'’ .,\x{00A0}\x{202F}-]*\s*$/u', $c) === 1);
            $n += count($chiffrees) >= 2 ? 1 : 0;
        }
        return $n;
    }

    /**
     * @param list<string> $lignes
     * @return array{list<LigneLue>, list<array{string, string}>, int|null, int|null}
     */
    private function appel(string $message, array $lignes): array
    {
        $corps = [
            'model' => $this->modele,
            'temperature' => 0,
            'messages' => [
                ['role' => 'system', 'content' => self::CONSIGNE],
                ['role' => 'user', 'content' => $message],
            ],
            'max_tokens' => self::JETONS_SORTIE_MAX,
        ];
        if ($this->sansRaisonnement) {
            $corps['reasoning_effort'] = 'none';
        }
        $reponse = $this->http->request('POST', rtrim($this->url, '/') . '/chat/completions', [
            'headers' => $this->cle === '' ? [] : ['Authorization' => 'Bearer ' . $this->cle],
            'json' => $corps,
            // Appelé pendant la requête de l'artisan, offre par offre : 25 s au plus mesurées chez Infomaniak (2026-11-20).
            'timeout' => 90,
            'max_duration' => 120,
        ]);
        $donnees = ReponseApi::lire($reponse, 'Lecture d\'offre');
        $usage = is_array($donnees['usage'] ?? null) ? $donnees['usage'] : [];
        $envoyes = is_int($usage['prompt_tokens'] ?? null) ? $usage['prompt_tokens'] : null;
        $recus = is_int($usage['completion_tokens'] ?? null) ? $usage['completion_tokens'] : null;
        if ($envoyes !== null && $recus !== null) {
            // Seulement des nombres, jamais le texte de l'offre.
            $this->journal->info(sprintf('offreGen, lecture d\'offre : %d jetons envoyés, %d reçus, modèle %s.', $envoyes, $recus, $this->modele));
        }
        $choix = is_array($donnees['choices'] ?? null) && is_array($donnees['choices'][0] ?? null) ? $donnees['choices'][0] : [];
        if (($choix['finish_reason'] ?? null) === 'length') {
            throw new \RuntimeException('Lecture d\'offre : réponse coupée par la limite de jetons de sortie.');
        }
        $message = is_array($choix['message'] ?? null) ? $choix['message'] : [];
        $sortie = is_string($message['content'] ?? null) ? $message['content'] : '';
        return [...$this->valider($sortie, $lignes), $envoyes, $recus];
    }

    /**
     * Les lignes rendues par le modèle, « n | désignation | unité | prix ». Une ligne sans barre est un commentaire,
     * ignoré ; une ligne à barres mal formée est refusée.
     *
     * @param list<string> $texte
     * @return array{list<LigneLue>, list<array{string, string}>}
     */
    private function valider(string $sortie, array $texte): array
    {
        $gardees = [];
        $refusees = [];
        foreach (preg_split('/\R/u', $sortie) ?: [] as $brute) {
            if (!str_contains($brute, '|')) {
                continue;
            }
            $cellules = array_map('trim', explode('|', trim($brute, " \t|")));
            if (count($cellules) < 4 || preg_match('/^\d+$/', $cellules[0]) !== 1) {
                $refusees[] = [mb_substr(trim($brute), 0, 100), 'ligne illisible'];
                continue;
            }
            $n = (int) array_shift($cellules);
            $prixEcrit = (string) array_pop($cellules);
            $ecrite = mb_substr((string) array_pop($cellules), 0, 20);
            $designation = mb_substr(ExtracteurApi::texte((string) preg_replace('/\s+/u', ' ', implode(' ', $cellules))), 0, 255);
            if ($designation === '') {
                $refusees[] = ['', 'désignation absente'];
                continue;
            }
            if ($n < 1 || $n > count($texte)) {
                $refusees[] = [$designation, 'ligne hors du texte'];
                continue;
            }
            $centimes = self::nombres($prixEcrit)[0] ?? null;
            $centimes = $centimes !== null && $centimes > 0 && $centimes <= Saisie::PRIX_MAX_CENTIMES ? $centimes : null;
            $ou = $centimes === null ? null : self::ligneDuPrix($texte, $n, $centimes);
            if ($centimes === null || $ou === null) {
                $refusees[] = [$designation, 'prix absent du texte'];
                continue;
            }
            if (self::estUnMontant($texte[$ou - 1], $centimes)) {
                $refusees[] = [$designation, 'montant de la ligne pris pour le prix unitaire'];
                continue;
            }
            $gardees[] = new LigneLue($designation, UniteLue::normaliser($ecrite === '' ? 'forfait' : $ecrite), $ecrite, $centimes, $ou);
        }
        return [$gardees, $refusees];
    }

    /**
     * La ligne du texte, près de celle que cite le modèle, où ce prix est écrit ; null s'il n'y est pas.
     *
     * @param list<string> $texte
     */
    private static function ligneDuPrix(array $texte, int $n, int $centimes): ?int
    {
        $ordre = [$n];
        for ($d = 1; $d <= self::FENETRE_APRES; $d++) {
            $ordre[] = $n + $d;
            if ($d <= self::FENETRE_AVANT) {
                $ordre[] = $n - $d;
            }
        }
        foreach ($ordre as $i) {
            if (isset($texte[$i - 1]) && in_array($centimes, self::nombres($texte[$i - 1]), true)) {
                return $i;
            }
        }
        return null;
    }

    /**
     * Vrai quand ce prix est le plus grand nombre de sa ligne et le produit de deux autres nombres de la ligne :
     * « 62 | m2 | 18.50 | 1'147.00 », prix proposé 1147.
     */
    private static function estUnMontant(string $ligne, int $centimes): bool
    {
        $nombres = self::nombres($ligne);
        if ($nombres === [] || $centimes < max($nombres)) {
            return false;
        }
        $autres = array_values(array_filter($nombres, static fn (int $x): bool => $x !== $centimes && $x > 0));
        foreach ($autres as $i => $a) {
            foreach ($autres as $j => $b) {
                // Deux nombres en centimes : leur produit est en dix-millièmes. Tolérance : l'arrondi aux 5 centimes.
                if ($i < $j && abs($a * $b / 100 - $centimes) <= 5) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Les nombres écrits dans une ligne, en centimes : « 18.50 », « 18,50 », « 1'250.00 », « 4 506,30 », « 78.- », « 14,8995 ».
     *
     * @return list<int>
     */
    public static function nombres(string $ligne): array
    {
        preg_match_all('/(?<![\d.,\'’])(\d{1,3}(?:[ \'’\x{00A0}\x{202F}]\d{3})+|\d+)(?:[.,](\d{1,4})(?!\d))?/u', $ligne, $trouves, PREG_SET_ORDER);
        $nombres = [];
        foreach ($trouves as $t) {
            $entier = (int) preg_replace('/\D/u', '', $t[1]);
            // Jusqu'à quatre décimales (« 14,8995 » dans un devis public du banc), arrondies au centime.
            $centimes = isset($t[2]) ? (int) round((int) str_pad($t[2], 4, '0') / 100) : 0;
            if ($entier <= Saisie::PRIX_MAX_CENTIMES / 100) {
                $nombres[] = $entier * 100 + $centimes;
            }
        }
        return $nombres;
    }
}
