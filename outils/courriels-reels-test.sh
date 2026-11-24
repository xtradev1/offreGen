#!/usr/bin/env bash
# Fenêtre d'envoi réel des courriels du site de test (Xavier, 2026-11-24 : « on va les limiter à une période de 1 ou
# 2 heures […] tester que ça envoie à tout le monde […] ensuite on repasse en test en mode sécurisé »).
#
# Pendant la fenêtre, chaque courriel part chez son vrai destinataire et laisse une ligne dans le journal du site.
# À l'heure dite, le site repasse seul en redirection vers COURRIELS_REDIRIGER_VERS : rien à éteindre. Le code
# n'ouvre rien pour une fin à plus de 2 heures (MessagerieRedirigee::FENETRE_MAX_SECONDES).
#
# Ne se lance qu'à la demande de Xavier : il ouvre l'envoi vers de vraies adresses.
#
# Usage : outils/courriels-reels-test.sh <minutes, 1 à 120>   ouvre la fenêtre
#         outils/courriels-reels-test.sh fermer               la ferme tout de suite
#         outils/courriels-reels-test.sh etat                 dit si elle est ouverte
set -euo pipefail

CIBLE="3p81e5_easytest@3p81e5.ftp.infomaniak.com"
CLE="$HOME/.ssh/offregen_test"
ENV_DISTANT="sites/easy-offres.xtradev.ch/partage/.env"
SSH=(ssh -i "$CLE" -o IdentitiesOnly=yes -o BatchMode=yes -o ConnectTimeout=15 -p 22 "$CIBLE")
choix="${1:-}"

case "$choix" in
  fermer)
    "${SSH[@]}" "sed -i '/^COURRIELS_ENVOI_REEL_JUSQU_A=/d' $ENV_DISTANT"
    echo "Fenêtre fermée : tous les courriels du site de test repartent vers l'adresse de redirection."
    ;;
  etat)
    fin="$("${SSH[@]}" "grep -E '^COURRIELS_ENVOI_REEL_JUSQU_A=' $ENV_DISTANT | cut -d= -f2" || true)"
    if [ -z "$fin" ] || [ "$(date -u +%s)" -ge "$(date -u -d "$fin" +%s)" ]; then
      echo "Fermée : tout est redirigé."
    else
      echo "Ouverte jusqu'à $(TZ=Europe/Zurich date -d "$fin" '+%H:%M') (heure suisse)."
    fi
    ;;
  ''|*[!0-9]*)
    echo "Usage : $0 <minutes, 1 à 120> | fermer | etat" >&2
    exit 1
    ;;
  *)
    if [ "$choix" -lt 1 ] || [ "$choix" -gt 120 ]; then
      echo "REFUS : de 1 à 120 minutes." >&2
      exit 1
    fi
    "${SSH[@]}" "grep -Eq \"^COURRIELS_REDIRIGER_VERS=['\\\"]?[^'\\\" ]+@\" $ENV_DISTANT" \
      || { echo "REFUS : pas de COURRIELS_REDIRIGER_VERS sur le test, la fenêtre n'aurait pas de fin." >&2; exit 1; }
    fin="$(date -u -d "+$choix minutes" +%Y-%m-%dT%H:%M:%SZ)"
    "${SSH[@]}" "sed -i '/^COURRIELS_ENVOI_REEL_JUSQU_A=/d' $ENV_DISTANT && echo 'COURRIELS_ENVOI_REEL_JUSQU_A=$fin' >> $ENV_DISTANT"
    echo "Fenêtre ouverte : les courriels du site de test partent chez leurs vrais destinataires jusqu'à $(TZ=Europe/Zurich date -d "$fin" '+%H:%M') (heure suisse)."
    echo "Ensuite, tout repart seul vers l'adresse de redirection. Pour fermer avant : $0 fermer"
    ;;
esac
