<?php

declare(strict_types=1);

// Sous-chantier 30, question de Xavier du 2026-11-24 : une règle de « réflexion » dans la consigne aide-t-elle le
// modèle à retrouver un produit du catalogue derrière un mot mal transcrit ? Même extracteur que l'application,
// consigne actuelle (A) contre consigne avec la règle (B). L'application n'est pas modifiée : la variante B est une
// copie de la classe chargée à part. Chaque cas : produits attendus (cibles) et produits interdits (piège).
// Usage : php banc_consigne.php <copie du dépôt> <dossier des données> [passes]

use OffreGen\Compte\Activite;
use OffreGen\Http\Conteneur;
use OffreGen\Offre\ExtracteurApi;
use Psr\Log\AbstractLogger;

[$_, $depot, $donnees] = $argv;
$passes = (int) ($argv[3] ?? 3);
require "$depot/vendor/autoload.php";

const TARIF = [0.40, 3.00]; // CHF HT par million de jetons, Qwen3.8-27B (outils/banc-corpus.php)
const REGLE = "        - La dictée vient d'une reconnaissance vocale, qui écrit parfois un mot du métier de travers, en mots qui se prononcent presque pareil. Quand un mot n'a pas de sens dans une offre d'artisan, demande-toi quel mot du catalogue ou du métier il fait entendre, et garde la lecture la plus logique pour cette offre. Ne rattache pas au catalogue un travail qui ne lui ressemble que par le son.\n";

$source = (string) file_get_contents("$depot/src/Offre/ExtracteurApi.php");
$fin = "        - Jamais de tiret long : une virgule à la place.\n        TXT;";
$pos = strpos($source, $fin);
if ($pos === false || !str_contains($source, 'final class ExtracteurApi ')) {
    exit("Ancre introuvable dans ExtracteurApi.php\n");
}
$variante = substr_replace($source, REGLE, $pos, 0);
$variante = str_replace('final class ExtracteurApi ', 'final class ExtracteurApiRegle ', $variante);
$fichier = sys_get_temp_dir() . '/ExtracteurApiRegle.php';
file_put_contents($fichier, $variante);
require $fichier;
if (!str_contains(\OffreGen\Offre\ExtracteurApiRegle::consigne(Activite::Batiment), 'reconnaissance vocale')
    || str_contains(ExtracteurApi::consigne(Activite::Batiment), 'reconnaissance vocale')) {
    exit("Variante mal construite\n");
}

$journal = new class () extends AbstractLogger {
    /** @var list<array{int, int}> */
    public array $jetons = [];

    public function log($level, \Stringable|string $message, array $context = []): void
    {
        if (preg_match('/(\d+) jetons envoyés, (\d+) reçus/u', (string) $message, $m) === 1) {
            $this->jetons[] = [(int) $m[1], (int) $m[2]];
        }
    }
};
$c = Conteneur::depuisEnvironnement($depot);
$args = [$c->http(), $c->parametre('EXTRACTION_URL'), $c->parametre('EXTRACTION_CLE'), $c->parametre('EXTRACTION_MODELE'), $journal,
    $c->parametre('EXTRACTION_SANS_RAISONNEMENT', '1') === '1'];
$extracteurs = ['A actuelle' => new ExtracteurApi(...$args), 'B règle' => new \OffreGen\Offre\ExtracteurApiRegle(...$args)];

$produits = json_decode((string) file_get_contents("$donnees/bc_produits.json"), true, flags: JSON_THROW_ON_ERROR);
$dictees = array_column(json_decode((string) file_get_contents("$donnees/bc_dictees.json"), true, flags: JSON_THROW_ON_ERROR), null, 'id');
$catalogue = static fn (int $artisan): array => array_values(array_map(static function (array $p): array {
    unset($p['artisan_id']);
    return $p;
}, array_filter($produits, static fn (array $p): bool => $p['artisan_id'] === $artisan)));

// [nom, artisan, transcription, cibles (produits attendus), interdits]
$cas = [
    ['R35 à la quai', 29, $dictees[35]['transcription'], [374], []],
    ['R34 travers teint, plaintes', 29, $dictees[34]['transcription'], [380, 373], []],
    ['R36 plaintes', 29, $dictees[36]['transcription'], [373], []],
    ['R14 crépit (maçon)', 20, $dictees[14]['transcription'], [300], []],
    ['R23 tuyats', 5, $dictees[23]['transcription'], [316], []],
    ['R24 tuyats', 5, $dictees[24]['transcription'], [316], []],
    ['S1 saules, pont sage, dans pression', 29, "Alors, offre pour Monsieur Exemple à Delémont. Dans le séjour, on commence par la protection des saules et des meubles. Ensuite le rebouchage et pont sage des murs, environ 40 mètres carrés. Puis une couche dans pression sur les 40 mètres carrés, et deux couches de peinture.", [368, 369, 370, 371], []],
    ['S2 timoisissure, fa sade, chats faudages', 29, "Pour la salle de bain, peinture en timoisissure au plafond, 8 mètres carrés. Dehors, la peinture de fa sade, 120 mètres carrés, avec les chats faudages, montage et location, 120 mètres carrés.", [375, 376, 377], []],
    ['S3 laquais les plaintes, rejet', 29, "Dans le couloir, il faut laquais les plaintes, 25 mètres. Les deux portes de la cuisine à laquer des deux côtés. Et compte trois heures de rejet pour les retouches.", [373, 374, 379], []],
    ['N1 piège poignée, tringle', 29, "Dans la chambre, il faut changer la poignée de la porte, 40 francs, et poser une tringle à rideaux, 60 francs. Deux couches sur les murs, 30 mètres carrés.", [371], [374, 373, 368]],
];

$total = [];
foreach ($extracteurs as $nomX => $x) {
    $total[$nomX] = ['cibles' => 0, 'sur' => 0, 'interdits' => 0, 'lignes' => 0, 'ct' => 0.0, 'n' => 0, 'echecs' => 0];
}
foreach ($cas as [$nom, $artisan, $texte, $cibles, $interdits]) {
    echo "== $nom\n";
    foreach ($extracteurs as $nomX => $x) {
        for ($k = 1; $k <= $passes; $k++) {
            $journal->jetons = [];
            try {
                $r = $x->extraire($texte, $catalogue($artisan), Activite::Batiment);
            } catch (\Throwable $e) {
                echo "  $nomX passe $k : échec, {$e->getMessage()}\n";
                $total[$nomX]['echecs']++;
                continue;
            }
            $ids = array_map(static fn (object $l): ?int => $l->produitId, $r->lignes);
            $trouves = array_values(array_intersect($cibles, $ids));
            $mauvais = array_values(array_intersect($interdits, $ids));
            $ct = (array_sum(array_column($journal->jetons, 0)) * TARIF[0] + array_sum(array_column($journal->jetons, 1)) * TARIF[1]) / 1e6 * 100;
            $t = &$total[$nomX];
            $t['cibles'] += count($trouves); $t['sur'] += count($cibles); $t['interdits'] += count($mauvais);
            $t['lignes'] += count($r->lignes); $t['ct'] += $ct; $t['n']++;
            unset($t);
            $manque = array_diff($cibles, $ids);
            printf("  %s passe %d : cibles %d/%d%s%s, %d lignes, %.2f ct | %s\n", $nomX, $k, count($trouves), count($cibles),
                $manque === [] ? '' : ' (manque ' . implode(', ', $manque) . ')', $mauvais === [] ? '' : ', INTERDIT ' . implode(', ', $mauvais),
                count($r->lignes), $ct, implode(' ; ', array_map(static fn (object $l): string => ($l->produitId ?? 'libre') . ' ' . $l->designation, $r->lignes)));
        }
    }
}
echo "\n";
foreach ($total as $nomX => $t) {
    printf("%s : cibles %d/%d (%.0f %%), produits interdits %d, %.1f lignes par offre, %.2f ct par offre, %d passes, %d échecs\n", $nomX,
        $t['cibles'], $t['sur'], 100 * $t['cibles'] / max(1, $t['sur']), $t['interdits'], $t['lignes'] / max(1, $t['n']), $t['ct'] / max(1, $t['n']), $t['n'], $t['echecs']);
}
@unlink($fichier);
