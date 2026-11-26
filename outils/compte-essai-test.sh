#!/usr/bin/env bash
# Compte d'essai sur le site de test (https://easy-offres.xtradev.ch), depuis le poste de travail, en une commande.
# Le mot de passe s'affiche une seule fois, dans ce terminal : à transmettre à l'artisan, rien n'en garde la trace.
#
#   bash outils/compte-essai-test.sh creer <courriel> "<entreprise>" <offres>   crée le compte, N offres en tout
#   bash outils/compte-essai-test.sh offres <courriel> <N|aucun>               change le total, ou lève le plafond
#
# En production, la clé SSH n'accepte que le déploiement : les comptes s'y créeront depuis l'administration.
set -euo pipefail

CLE="$HOME/.ssh/offregen_test"
CIBLE="3p81e5_easytest@3p81e5.ftp.infomaniak.com"
PORT=22
APP="sites/easy-offres.xtradev.ch"
PHP_DISTANT="php-8.5"

usage() {
  sed -n '5,6p' "$0" | sed 's/^# *//'
  exit 2
}

[ $# -ge 1 ] || usage
case "$1" in
  creer)
    [ $# -eq 4 ] || usage
    [[ "$4" =~ ^[0-9]+$ ]] || usage
    commande=(bin/creer-compte "$2" "$3" "--offres=$4")
    ;;
  offres)
    [ $# -eq 3 ] || usage
    [[ "$3" =~ ^([0-9]+|aucun)$ ]] || usage
    commande=(bin/offres-essai "$2" "$3")
    ;;
  *) usage ;;
esac

# Chaque argument part entre guillemets simples : un nom d'entreprise avec espaces ou apostrophe passe tel quel.
distant="cd \"\$HOME/$APP/current\" && $PHP_DISTANT"
for a in "${commande[@]}"; do
  distant+=" '${a//\'/\'\\\'\'}'"
done
exec ssh -i "$CLE" -o IdentitiesOnly=yes -o BatchMode=yes -o ConnectTimeout=15 -p "$PORT" "$CIBLE" "$distant"
