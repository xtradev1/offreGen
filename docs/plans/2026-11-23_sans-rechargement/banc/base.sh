#!/bin/sh
# Instantané de la base de mesure : « sh base.sh garder » l'écrit, « sh base.sh remettre » la remet. Lancé en root.
ICI=/home/xavie/offreGen-fluidite-banc
case "$1" in
  garder) mariadb-dump --skip-comments --single-transaction offregen_fluidite_vue > "$ICI/instantane.sql" && wc -c < "$ICI/instantane.sql" ;;
  remettre) mariadb offregen_fluidite_vue < "$ICI/instantane.sql" ;;
  *) echo "usage : base.sh garder|remettre" ; exit 1 ;;
esac
