<?php

declare(strict_types=1);

/**
 * Refait src/Adresse/localites.php depuis le répertoire officiel des localités de swisstopo (NPA et localité,
 * Suisse et Liechtenstein). Données libres, source à citer : « swisstopo, répertoire officiel des localités ».
 *
 * 1. Télécharger https://data.geo.admin.ch/ch.swisstopo-vd.ortschaftenverzeichnis_plz/ortschaftenverzeichnis_plz/ortschaftenverzeichnis_plz_4326.csv.zip
 * 2. Le décompresser dans un dossier vide.
 * 3. php outils/localites.php <dossier>/AMTOVZ_CSV_WGS84/AMTOVZ_CSV_WGS84.csv
 *
 * Le répertoire change quelques fois par an (fusions de communes) : le refaire avant une mise en production.
 */

if ($argc !== 2 || !is_file($argv[1])) {
    fwrite(STDERR, "Usage : php outils/localites.php AMTOVZ_CSV_WGS84.csv\n");
    exit(1);
}
$f = fopen($argv[1], 'r');
if ($f === false) {
    exit(1);
}
$entete = fgetcsv($f, null, ';', '"', '');
if (!is_array($entete)) {
    exit(1);
}
$entete[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $entete[0]);
$colNom = array_search('Ortschaftsname', $entete, true);
$colNpa = array_search('PLZ4', $entete, true);
if ($colNom === false || $colNpa === false) {
    fwrite(STDERR, "Colonnes Ortschaftsname et PLZ4 introuvables : le format a changé.\n");
    exit(1);
}
$parNpa = [];
while (($l = fgetcsv($f, null, ';', '"', '')) !== false) {
    $npa = trim((string) ($l[$colNpa] ?? ''));
    $nom = trim((string) ($l[$colNom] ?? ''));
    if (preg_match('/^\d{4}$/', $npa) !== 1 || $nom === '') {
        continue;
    }
    $parNpa[$npa][$nom] = true;
}
ksort($parNpa, SORT_STRING);
$sortie = "<?php\n\n// Fabriqué par outils/localites.php le " . date('Y-m-d') . ". Source : swisstopo, répertoire officiel des localités.\n"
    . "// Ne pas modifier à la main.\n\nreturn [\n";
foreach ($parNpa as $npa => $noms) {
    $noms = array_keys($noms);
    sort($noms, SORT_STRING);
    $sortie .= "    '$npa' => [" . implode(', ', array_map(static fn (string $n): string => var_export($n, true), $noms)) . "],\n";
}
$sortie .= "];\n";
$cible = dirname(__DIR__) . '/src/Adresse/localites.php';
file_put_contents($cible, $sortie);
echo count($parNpa), " NPA écrits dans src/Adresse/localites.php\n";
