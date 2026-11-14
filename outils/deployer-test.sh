#!/usr/bin/env bash
# Déploie le commit courant sur le site de test (https://easy-offres.xtradev.ch), depuis le poste de travail.
#
# Entorse au protocole (docs/DEPLOIEMENT.md), décidée le 2026-11-14 : en attendant le workflow GitHub du
# sous-chantier 02, Claude déploie le test par SSH, avec les mêmes étapes que ce workflow. Le test ne contient
# que des données inventées : ni mode maintenance ni sauvegarde avant les migrations. Jamais pour la production.
#
# Étapes : arbre propre et commit poussé ; tests et analyse ; construction (git archive, composer sans dev) ;
# envoi dans releases/<commit>/ ; liens vers partage/ (.env et données) ; migrations par l'utilisateur de
# structure ; bascule de current ; tâche cron du traitement ; sonde d'exposition avec le commit attendu.
# Sonde rouge : retour à la version précédente s'il y en a une.
#
# Usage : outils/deployer-test.sh
set -euo pipefail

CIBLE="3p81e5_easytest@3p81e5.ftp.infomaniak.com"
PORT=22
CLE="$HOME/.ssh/offregen_test"
URL="https://easy-offres.xtradev.ch"
PHP_DISTANT="php-8.5"
GARDER=5
TEMOINS="/.sonde-temoin /sonde-temoin.php /sonde-temoin.bin /sonde-temoin.sql"

SSH=(ssh -i "$CLE" -o IdentitiesOnly=yes -o BatchMode=yes -o ConnectTimeout=15 -p "$PORT" "$CIBLE")
racine="$(cd "$(dirname "$0")/.." && pwd)"
cd "$racine"
etape() { printf '\n== %s\n' "$1"; }

etape "Contrôles du dépôt"
git diff --quiet && git diff --cached --quiet || { echo "REFUS : modifications non commitées."; exit 1; }
commit="$(git rev-parse HEAD)"
git fetch -q origin
[ -n "$(git branch -r --contains "$commit")" ] || { echo "REFUS : $commit n'est pas poussé sur GitHub."; exit 1; }
echo "commit $commit ($(git log -1 --format=%s))"

etape "Tests et analyse"
sortie="$(env APP_ENV=test vendor/bin/phpunit 2>&1)" || { printf '%s\n' "$sortie" | tail -15; echo "REFUS : tests rouges."; exit 1; }
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
"${SSH[@]}" "mkdir -p offregen/releases/$commit"
rsync -a --delete -e "ssh -i $CLE -o IdentitiesOnly=yes -o BatchMode=yes -p $PORT" "$construction/" "$CIBLE:offregen/releases/$commit/"
echo "offregen/releases/$commit"

etape "Liens, migrations, bascule, cron"
precedent="$("${SSH[@]}" bash -s -- "$commit" "$PHP_DISTANT" "$GARDER" <<'DISTANT'
set -euo pipefail
commit="$1"; php="$2"; garder="$3"
cd "$HOME/offregen"
r="releases/$commit"
ln -sfn ../../partage/.env "$r/.env"
for d in audio logos log sessions courriels; do rm -rf "$r/var/$d"; ln -sfn "../../../partage/var/$d" "$r/var/$d"; done
# Le .env doit se lire avant tout. Le message de Dotenv recopie la ligne fautive, mot de passe compris
# (incident du 2026-11-14) : seul son numéro sort d'ici.
( cd "$r" && "$php" -r '
  require "vendor/autoload.php";
  try { (new Symfony\Component\Dotenv\Dotenv())->parse((string) file_get_contents(".env"), ".env"); }
  catch (Symfony\Component\Dotenv\Exception\FormatException $e) {
    fwrite(STDERR, "REFUS : .env illisible à la ligne " . $e->getContext()->getLineno() . " (valeur avec espace, # ou \$ : la mettre entre apostrophes).\n");
    exit(1);
  }' ) || exit 1
( cd "$r" && "$php" vendor/bin/doctrine-migrations migrate --no-interaction --allow-no-migration >&2 )
precedent="$(readlink current 2>/dev/null || true)"
ln -sfn "$r" current.nouveau && mv -Tf current.nouveau current
# Garde les dernières versions pour le retour arrière ; jamais celle en service.
for v in $(ls -1dt releases/*/ | sed 's#/$##' | tail -n +"$((garder + 1))"); do
  if [ "$v" != "$r" ]; then rm -rf "$v"; fi
done
ligne="* * * * * cd \$HOME/offregen/current && $php bin/traiter-file --silencieux >> \$HOME/offregen/partage/var/log/cron.log 2>&1"
( crontab -l 2>/dev/null | grep -v 'bin/traiter-file' || true; echo "$ligne" ) | crontab -
echo "$precedent"
DISTANT
)"
echo "en service : releases/$commit ; précédente : ${precedent:-aucune}"

etape "Sonde d'exposition"
if SONDE_TEMOINS="$TEMOINS" scripts/securite/sonde-exposition.sh "$URL" "$commit"; then
  echo; echo "DÉPLOYÉ : $commit sur $URL"
  exit 0
fi
if [ -n "$precedent" ] && [ "$precedent" != "releases/$commit" ]; then
  etape "Sonde rouge : retour à $precedent"
  "${SSH[@]}" "cd offregen && ln -sfn '$precedent' current.nouveau && mv -Tf current.nouveau current"
  SONDE_TEMOINS="$TEMOINS" scripts/securite/sonde-exposition.sh "$URL" "$(basename "$precedent")" || echo "ALERTE : la version précédente est rouge elle aussi."
else
  echo "Sonde rouge, aucune version précédente : rien à rétablir."
fi
exit 1
