<?php
// Joue la requête de _q.sql sur la base locale de la copie de relecture (jamais le test, jamais la production).
require '/home/xavie/offreGen-relecture/vendor/autoload.php';
$env = OffreGen\Http\Conteneur::lireEnvironnement('/home/xavie/offreGen-relecture');
if (($env['DB_NOM'] ?? '') !== 'offregen_relecture') {
    fwrite(STDERR, "REFUS : base " . ($env['DB_NOM'] ?? '?') . "\n");
    exit(1);
}
$pdo = (new OffreGen\Donnees\FabriqueConnexion($env))->pdo();
$q = (string) file_get_contents(__DIR__ . '/_q.sql');
$r = $pdo->query($q);
echo json_encode(str_starts_with(ltrim(strtoupper($q)), 'SELECT') ? $r->fetchAll(PDO::FETCH_ASSOC) : ['lignes' => $r->rowCount()]);
