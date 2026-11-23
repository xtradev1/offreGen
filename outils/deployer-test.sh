#!/usr/bin/env bash
# Déploie le commit courant sur le site de test (https://easy-offres.xtradev.ch), depuis le poste de travail.
#
# Entorse au protocole (docs/DEPLOIEMENT.md), décidée le 2026-11-14 : en attendant le workflow GitHub du
# sous-chantier 02, Claude déploie le test par SSH, avec les mêmes étapes que ce workflow. Le test ne contient
# que des données inventées : ni mode maintenance ni sauvegarde avant les migrations. Jamais pour la production.
#
# Disposition, dans le dossier du site comme Infomaniak le prévoit (Xavier, 2026-11-15) :
#   sites/easy-offres.xtradev.ch/public   -> current/public   (racine web réglée dans le Manager, seul dossier servi)
#   sites/easy-offres.xtradev.ch/current  -> releases/<commit>
#   sites/easy-offres.xtradev.ch/releases/<commit>/
#   sites/easy-offres.xtradev.ch/partage/ (.env, voix, logos, journaux, sessions ; fermé)
#
# Étapes : arbre propre et commit poussé ; verrou (un déploiement à la fois) ; tests sur une base réservée et
# analyse ; construction (git archive, composer sans dev) ; envoi dans releases/<commit>/ ; liens vers partage/ ;
# lecture du .env ; migrations par l'utilisateur de structure ; bascule de current ; tâche cron du traitement ;
# sonde d'exposition avec le commit attendu. Sonde rouge : retour à la version précédente s'il y en a une.
#
# Usage : outils/deployer-test.sh
#   DEPLOIEMENT_BASE_TESTS=<base>_test  autre base pour les tests du déploiement (elle est vidée, tables comprises)
#   RETOUR_ARRIERE=1                    remettre en service un commit plus ancien que la version en service
set -euo pipefail

# Base des tests du déploiement (2026-11-23). La base que désigne .env.test.local sert aussi aux suites des
# sessions qui travaillent en parallèle : elles la vidaient pendant la suite du déploiement, refusé à tort
# (« tests rouges », trois refus de suite ce jour-là). Les tests du déploiement tournent sur une base réservée,
# reconstruite de zéro par les migrations du commit : elle a la structure de ce commit, quoi qu'on y ait joué
# avant. À créer une fois sur le poste : docs/DEPLOIEMENT.md, « Site de test ».
BASE_TESTS="${DEPLOIEMENT_BASE_TESTS:-offregen_deploiement_test}"
# Chemin fixe, sans TMPDIR : toutes les sessions du poste doivent tomber sur le même fichier.
VERROU="/tmp/offregen-deployer-test.verrou"
ATTENTE_VERROU=1800

CIBLE="3p81e5_easytest@3p81e5.ftp.infomaniak.com"
PORT=22
CLE="$HOME/.ssh/offregen_test"
URL="https://easy-offres.xtradev.ch"
APP="sites/easy-offres.xtradev.ch"
PHP_DISTANT="php-8.5"
GARDER=5
TEMOINS="/.sonde-temoin /sonde-temoin.php /sonde-temoin.bin /sonde-temoin.sql"

SSH=(ssh -i "$CLE" -o IdentitiesOnly=yes -o BatchMode=yes -o ConnectTimeout=15 -p "$PORT" "$CIBLE")
racine="$(cd "$(dirname "$0")/.." && pwd)"
cd "$racine"
etape() { printf '\n== %s\n' "$1"; }
# La sonde part du serveur de test vers l'adresse publique du site (même proxy qu'un visiteur). Depuis WSL,
# des requêtes se perdent avant Apache (2026-11-15 : 2 à 5 sur 20, aucune depuis Windows ni absentes du
# journal du serveur) : une sonde lancée de WSL serait rouge sans raison et déclencherait un retour arrière.
sonde() { "${SSH[@]}" "SONDE_TEMOINS='$TEMOINS' bash -s -- '$URL' '$1'" < scripts/securite/sonde-exposition.sh; }

etape "Contrôles du dépôt"
git diff --quiet && git diff --cached --quiet || { echo "REFUS : modifications non commitées."; exit 1; }
commit="$(git rev-parse HEAD)"
git fetch -q origin
[ -n "$(git branch -r --contains "$commit")" ] || { echo "REFUS : $commit n'est pas poussé sur GitHub."; exit 1; }
echo "commit $commit ($(git log -1 --format=%s))"

etape "Verrou"
# Deux déploiements en parallèle se disputeraient la base des tests et le lien current. Le verrou tient au
# processus : il tombe avec lui, même tué.
exec 9> "$VERROU"
if ! flock -n 9; then
  echo "Un autre déploiement est en cours : attente, $((ATTENTE_VERROU / 60)) minutes au plus."
  flock -w "$ATTENTE_VERROU" 9 || { echo "REFUS : l'autre déploiement tient encore le verrou après $((ATTENTE_VERROU / 60)) minutes."; exit 1; }
fi
echo "verrou pris"
# Pendant l'attente, une autre session a pu déployer plus loin sur la branche : remettre ce commit en service
# retirerait son travail du site de test.
en_service="$("${SSH[@]}" "cat $APP/current/REVISION 2>/dev/null" || true)"
if [[ "$en_service" =~ ^[0-9a-f]{40}$ ]] && [ "$en_service" != "$commit" ] && [ "${RETOUR_ARRIERE:-0}" != "1" ] \
   && git merge-base --is-ancestor "$commit" "$en_service" 2>/dev/null; then
  echo; echo "DÉJÀ EN SERVICE : la version du site de test ($en_service) contient $commit. Rien à déployer."
  echo "Pour revenir exprès à ce commit : RETOUR_ARRIERE=1 outils/deployer-test.sh"
  exit 0
fi

etape "Tests et analyse"
# DB_NOM posé ici l'emporte sur .env.test.local (Conteneur::lireEnvironnement lit le système en dernier).
tests=(env APP_ENV=test DB_NOM="$BASE_TESTS")
# Le nom est relu là où il sert, sur la connexion elle-même : rien n'est supprimé hors d'une base *_test.
"${tests[@]}" php -r '
  require "vendor/autoload.php";
  $p = require "migrations-db.php";
  if (!str_ends_with($p["dbname"], "_test")) {
    fwrite(STDERR, "REFUS : la base des tests du déploiement doit finir par _test (" . $p["dbname"] . ").\n");
    exit(1);
  }
  try {
    $c = Doctrine\DBAL\DriverManager::getConnection($p);
    $tables = $c->fetchFirstColumn("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = ?", ["BASE TABLE"]);
    $c->executeStatement("SET FOREIGN_KEY_CHECKS = 0");
    foreach ($tables as $t) { $c->executeStatement("DROP TABLE `" . str_replace("`", "``", $t) . "`"); }
  } catch (Throwable $e) {
    // Le code seul : le message complet de la connexion reste hors des journaux.
    fwrite(STDERR, "REFUS : base " . $p["dbname"] . " inaccessible (erreur " . $e->getCode() . "). La créer une fois sur le poste : docs/DEPLOIEMENT.md, « Site de test ».\n");
    exit(1);
  }
  echo "base des tests : " . $p["dbname"] . ", reconstruite de zéro\n";' || exit 1
sortie="$("${tests[@]}" vendor/bin/doctrine-migrations migrate --no-interaction --allow-no-migration 2>&1)" \
  || { printf '%s\n' "$sortie" | tail -15; echo "REFUS : les migrations ne passent pas sur une base vide."; exit 1; }
sortie="$("${tests[@]}" vendor/bin/phpunit 2>&1)" || { printf '%s\n' "$sortie" | tail -15; echo "REFUS : tests rouges."; exit 1; }
printf '%s\n' "$sortie" | tail -1
vendor/bin/phpstan analyse --no-progress > /dev/null 2>&1 || { echo "REFUS : analyse statique en erreur."; exit 1; }
echo "analyse statique : sans erreur"

etape "Construction"
construction="$(mktemp -d)"
trap 'rm -rf "$construction"' EXIT
git archive "$commit" | tar -x -C "$construction"
rm -rf "$construction"/{tests,docs,.claude,outils/ia-locale,phpunit.xml,phpstan.neon}
printf '%s\n' "$commit" > "$construction/REVISION"
mkdir -p "$construction/var/cache"
( cd "$construction" && composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader --quiet )
echo "$(du -sh "$construction" | cut -f1), $(find "$construction" -type f | wc -l) fichiers"

etape "Envoi"
"${SSH[@]}" "mkdir -p $APP/releases/$commit"
rsync -a --delete -e "ssh -i $CLE -o IdentitiesOnly=yes -o BatchMode=yes -p $PORT" "$construction/" "$CIBLE:$APP/releases/$commit/"
# Apache tourne sous un autre utilisateur : il doit traverser releases/ et la version pour servir public/
# (403 du 2026-11-15 sinon). Les autres hébergements du serveur sont arrêtés au dossier de l'hébergement
# (group:ldapusers:---). partage/ reste fermé : seul PHP, qui tourne sous l'utilisateur de l'hébergement, le lit.
"${SSH[@]}" "chmod 711 $APP/releases && chmod 755 $APP/releases/$commit && chmod 700 $APP/partage"
echo "$APP/releases/$commit"

etape "Liens, migrations, bascule, cron"
precedent="$("${SSH[@]}" bash -s -- "$APP" "$commit" "$PHP_DISTANT" "$GARDER" <<'DISTANT'
set -euo pipefail
app="$1"; commit="$2"; php="$3"; garder="$4"
cd "$HOME/$app"
r="releases/$commit"
ln -sfn ../../partage/.env "$r/.env"
# Mot de passe de structure à part, lu par les migrations seules (revue du 2026-11-18, M12).
[ -f partage/.env.structure ] && ln -sfn ../../partage/.env.structure "$r/.env.structure"
# alertes : l'état du plafond des courriels d'alerte (2026-11-18), gardé d'une version à l'autre.
for d in audio logos log sessions courriels alertes; do mkdir -p "partage/var/$d"; rm -rf "$r/var/$d"; ln -sfn "../../../partage/var/$d" "$r/var/$d"; done
# Le .env doit se lire avant tout. Le message de Dotenv recopie la ligne fautive, mot de passe compris
# (incident du 2026-11-14) : seul son numéro sort d'ici.
( cd "$r" && "$php" -r '
  require "vendor/autoload.php";
  try { (new Symfony\Component\Dotenv\Dotenv())->parse((string) file_get_contents(".env"), ".env"); }
  catch (Symfony\Component\Dotenv\Exception\FormatException $e) {
    fwrite(STDERR, "REFUS : .env illisible à la ligne " . $e->getContext()->getLineno() . " (valeur avec espace, # ou \$ : la mettre entre apostrophes).\n");
    exit(1);
  }' ) || exit 1
# Site de test : aucun courriel réel sans redirection vers une seule adresse (docs/SECURITE.md, revue du 2026-11-18).
if grep -Eq "^MESSAGERIE=['\"]?smtp" partage/.env && ! grep -Eq "^COURRIELS_REDIRIGER_VERS=['\"]?[^'\" ]+@" partage/.env; then
  echo "REFUS : MESSAGERIE=smtp sans COURRIELS_REDIRIGER_VERS dans partage/.env, le test enverrait de vrais courriels." >&2
  exit 1
fi
( cd "$r" && "$php" vendor/bin/doctrine-migrations migrate --no-interaction --allow-no-migration >&2 )
precedent="$(readlink current 2>/dev/null || true)"
ln -sfn "$r" current.nouveau && mv -Tf current.nouveau current
# Racine web du Manager : public, lien vers current/public.
[ "$(readlink public 2>/dev/null)" = "current/public" ] || echo "ALERTE : $app/public ne pointe pas sur current/public" >&2
# Garde les dernières versions pour le retour arrière ; jamais celle en service.
for v in $(ls -1dt releases/*/ | sed 's#/$##' | tail -n +"$((garder + 1))"); do
  if [ "$v" != "$r" ]; then rm -rf "$v"; fi
done
ligne="* * * * * cd \$HOME/$app/current && $php bin/traiter-file --silencieux >> \$HOME/$app/partage/var/log/cron.log 2>&1"
# Purge des audios chaque nuit à 3 h 17, éteinte tant que le .env ne porte pas la ligne exacte PURGE_AUDIO=1
# (décision Q1 de Xavier, 2026-11-18). La garde est dans la ligne cron, pas seulement dans le code : après un
# retour arrière, une version d'avant le 18.11 purgerait sans condition (revue du 2026-11-18).
purge="17 3 * * * grep -qx 'PURGE_AUDIO=1' \$HOME/$app/partage/.env && cd \$HOME/$app/current && $php bin/purger-audio >> \$HOME/$app/partage/var/log/cron.log 2>&1"
( crontab -l 2>/dev/null | grep -v -e 'bin/traiter-file' -e 'bin/purger-audio' || true; echo "$ligne"; echo "$purge" ) | crontab -
echo "$precedent"
DISTANT
)"
echo "en service : releases/$commit ; précédente : ${precedent:-aucune}"

etape "Attente de la nouvelle version"
# PHP garde quelque temps le chemin résolu de current (cache des chemins) : juste après la bascule, /sante
# peut encore annoncer l'ancien commit, et la sonde déclencherait un retour arrière à tort (2026-11-15).
"${SSH[@]}" bash -s -- "$URL" "$commit" <<'DISTANT' || echo "ALERTE : /sante n'annonce pas encore $commit, la sonde tranchera"
for i in $(seq 1 30); do
  curl -s -m 10 "$1/sante" | grep -q "\"commit\":\"$2\"" && { echo "/sante annonce $2 après $((i * 5 - 5)) s"; exit 0; }
  sleep 5
done
exit 1
DISTANT

etape "Sonde d'exposition"
if sonde "$commit"; then
  echo; echo "DÉPLOYÉ : $commit sur $URL"
  exit 0
fi
if [ -n "$precedent" ] && [ "$precedent" != "releases/$commit" ]; then
  etape "Sonde rouge : retour à $precedent"
  "${SSH[@]}" "cd $APP && ln -sfn '$precedent' current.nouveau && mv -Tf current.nouveau current"
  sonde "$(basename "$precedent")" || echo "ALERTE : la version précédente est rouge elle aussi."
else
  echo "Sonde rouge, aucune version précédente : rien à rétablir."
fi
exit 1
