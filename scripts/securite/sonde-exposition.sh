#!/usr/bin/env bash
# Sonde d'exposition : prouve depuis l'extérieur qu'un site ne laisse rien fuiter.
# Uniquement des GET sur une liste fermée de chemins. Un chemin absent passe par le contrôleur
# frontal de l'application : aucun chemin de cette liste ne doit avoir d'effet de bord.
#
# Usage : sonde-exposition.sh https://test.exemple.ch [commit-attendu]
#   commit-attendu : vérifie aussi /sante (bon commit, base joignable, migrations à jour).
#   SONDE_TEMOINS="/a /b" : fichiers témoins déployés, qui doivent répondre exactement 403.
#     Ils prouvent que les règles de refus sont actives : sans eux, un .env absent et un
#     .htaccess inactif donnent le même 404. SONDE_TEMOINS=aucun les saute et le signale.
#   SONDE_AUTH="utilisateur:mot-de-passe" : authentification en amont d'un environnement de test.
#   SONDE_CHEMINS_EN_PLUS="/a /b" : chemins interdits propres au projet.
#   SONDE_LOCAL=1 : accepte http:// pour le banc d'essai de cette sonde.
# Sortie : 0 verte, 1 rouge, 2 mauvais usage.
set -uo pipefail

BASE="${1:-}"
COMMIT="${2:-}"
[ -n "$BASE" ] || { echo "Usage : $0 URL [commit-attendu]"; exit 2; }
BASE="${BASE%/}"

case "$BASE" in
  https://*) HTTPS=1 ;;
  http://*)
    [ "${SONDE_LOCAL:-}" = "1" ] || { echo "REFUS : URL en http. SONDE_LOCAL=1 pour le banc d'essai."; exit 2; }
    HTTPS=0 ;;
  *) echo "REFUS : URL invalide : $BASE"; exit 2 ;;
esac

AUTH=()
[ -n "${SONDE_AUTH:-}" ] && AUTH=(-u "$SONDE_AUTH")

ECHECS=0
ok() { printf 'OK     %s\n' "$1"; }
ko() { printf 'ECHEC  %s\n' "$1"; ECHECS=$((ECHECS + 1)); }
code_de() { curl -sS "${AUTH[@]}" -o /dev/null -w '%{http_code}' --max-time 15 "$1" 2>/dev/null || true; }

# Témoin : sur un site éteint, tous les chemins interdits renverraient une erreur et la sonde serait verte.
TEMOIN="$(code_de "$BASE/")"
case "$TEMOIN" in
  2??|3??) ok "témoin / : $TEMOIN" ;;
  *) echo "ECHEC  témoin / : $TEMOIN. Site injoignable ou authentification manquante, aucun autre contrôle n'a de valeur."; echo "SONDE ROUGE"; exit 1 ;;
esac

# Un site qui répond 200 partout rendrait les refus ci-dessous sans valeur : on le détecte.
INEXISTANT="$(code_de "$BASE/sonde-chemin-inexistant-$RANDOM$RANDOM")"
[ "$INEXISTANT" = "404" ] && ok "chemin inexistant : 404" || ko "chemin inexistant : $INEXISTANT au lieu de 404"

TEMOINS="${SONDE_TEMOINS:-}"
if [ -z "$TEMOINS" ]; then
  ko "témoins de refus non déclarés : renseigner SONDE_TEMOINS, ou SONDE_TEMOINS=aucun avec sa raison dans docs/SECURITE.md"
elif [ "$TEMOINS" = "aucun" ]; then
  printf 'SAUTE  témoins de refus : règles de refus non prouvées\n'
else
  for CHEMIN in $TEMOINS; do
    CODE="$(code_de "$BASE$CHEMIN")"
    [ "$CODE" = "403" ] && ok "témoin refusé $CHEMIN : 403" || ko "témoin $CHEMIN : $CODE au lieu de 403 (règles inactives, ou fichier non déployé)"
  done
fi

CHEMINS="/.env /.env.local /.env.production /.git/config /.git/HEAD /.github/workflows/ \
/.htaccess /.user.ini /composer.json /composer.lock /package.json /package-lock.json \
/vendor/autoload.php /vendor/composer/installed.json /node_modules/ \
/storage/logs/laravel.log /logs/ /backup.sql /dump.sql /database.sql /docker-compose.yml \
/uploads/ ${SONDE_CHEMINS_EN_PLUS:-}"

for CHEMIN in $CHEMINS; do
  CODE="$(code_de "$BASE$CHEMIN")"
  case "$CODE" in
    401|403|404|410) ok "$CHEMIN : $CODE" ;;
    *) ko "$CHEMIN : $CODE (refus attendu : 401, 403, 404 ou 410)" ;;
  esac
done

if [ "$HTTPS" = "1" ]; then
  # Sans identifiants : ils ne doivent jamais partir en clair.
  HTTP_URL="http://${BASE#https://}/"
  REPONSE="$(curl -sS -o /dev/null -w '%{http_code} %{redirect_url}' --max-time 15 "$HTTP_URL" 2>/dev/null || true)"
  case "$REPONSE" in
    "301 https://"*|"308 https://"*) ok "http vers https : $REPONSE" ;;
    "401 "*)
      # Apache contrôle l'authentification avant la réécriture.
      if [ -n "${SONDE_AUTH:-}" ]; then ok "http refusé par l'authentification : 401"; else ko "http vers https : $REPONSE"; fi ;;
    *) ko "http vers https : $REPONSE" ;;
  esac
else
  printf 'SAUTE  http vers https et HSTS (banc local)\n'
fi

ENTETES="$(curl -sS "${AUTH[@]}" -D - -o /dev/null --max-time 15 "$BASE/" 2>/dev/null | tr -d '\r' | tr '[:upper:]' '[:lower:]')"
exige() { printf '%s\n' "$ENTETES" | grep -Eq "$1" && ok "en-tête : $2" || ko "en-tête absent : $2"; }
exige '^x-content-type-options: *nosniff' 'X-Content-Type-Options nosniff'
exige '^content-security-policy: ' 'Content-Security-Policy'
exige '^referrer-policy: ' 'Referrer-Policy'
exige '^(x-frame-options: |content-security-policy: .*frame-ancestors)' 'protection contre le cadrage'
[ "$HTTPS" = "1" ] && exige '^strict-transport-security: ' 'Strict-Transport-Security'
if printf '%s\n' "$ENTETES" | grep -q '^x-powered-by: '; then ko "en-tête à retirer : X-Powered-By"; else ok "X-Powered-By absent"; fi

if [ -n "$COMMIT" ]; then
  SANTE="$(curl -sS "${AUTH[@]}" --max-time 15 "$BASE/sante" 2>/dev/null || true)"
  printf 'LU     /sante : %s\n' "$SANTE"
  printf '%s' "$SANTE" | grep -Eq "\"commit\"[[:space:]]*:[[:space:]]*\"$COMMIT\"" && ok "commit déployé : $COMMIT" || ko "commit attendu absent de /sante : $COMMIT"
  printf '%s' "$SANTE" | grep -Eq '"base"[[:space:]]*:[[:space:]]*"ok"' && ok "base joignable" || ko "base non confirmée par /sante"
  printf '%s' "$SANTE" | grep -Eq '"migrations"[[:space:]]*:[[:space:]]*"ok"' && ok "migrations à jour" || ko "migrations non confirmées par /sante"
fi

if [ "$ECHECS" -gt 0 ]; then
  echo "SONDE ROUGE : $ECHECS échec(s)"
  exit 1
fi
echo "SONDE VERTE"
