# Livré, archivé du roadmap

Déplacé tel quel de `PROJECT_ROADMAP.md` le 2026-11-18.

- [x] Socle posé : `CODING_RULES.md`, `docs/SECURITE.md`, `docs/DEPLOIEMENT.md`, sonde d'exposition, `public/.htaccess`, fichiers témoins, `.gitignore`, `.gitattributes`.
- [x] Cadrage produit et scripts de mesure archivés dans `docs/plans/2026-11-05_mesures-ia/`.
- [x] Premier commit sur `main`, poussé sur GitHub le 2026-11-06.
- [x] Maquette d'ergonomie cliquable, trois métiers fictifs : `docs/plans/2026-11-06_maquette-ux/MAQUETTE-offre-vocale.html`. Versions 2 et 3 le 2026-11-06, version 4 le 2026-11-07, après les retours de Xavier.
- [x] Maquette fusionnée dans `main` (PR #1, 3a28c6f).
- [x] Environnement de développement dans WSL : PHP 8.5.12, Composer 2.11.1, MariaDB 10.11.14, clone `~/offreGen` (2026-11-07). PR `environnement-wsl` fusionnée par Xavier (9c0ce79). ffmpeg 6.1 ajouté pour les fichiers audio de test.
- [x] Sous-chantier 01 « socle et import » livré en code (2026-11-07), branche `chantier-voix-offre` : 89 tests, PHPStan niveau 8, parcours cliqué avec captures. Preuves dans `docs/plans/2026-11-07_voix-offre/01-socle-import/ETAT.md`.
- [x] Sous-chantier 10 « IA réelle en local » livré en code (2026-11-12) : une dictée fictive de 28 s devient une offre juste en 17 à 28 s. 121 tests, plus un test de bout en bout sur les vrais services.
