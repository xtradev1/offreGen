set -e
mariadb -e "DROP DATABASE IF EXISTS offregen_relecture; CREATE DATABASE offregen_relecture CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
su xavie -c 'cd /home/xavie/offreGen-relecture && vendor/bin/doctrine-migrations migrate --no-interaction --allow-no-migration > /dev/null 2>&1 && echo migrations ok && php bin/creer-compte admin-relecture@exemple.ch "XtraDev" > /home/xavie/.offregen-relecture-admin-compte && php bin/administrateur admin-relecture@exemple.ch oui && rm -f var/courriels/*.eml'
