# Relecture des textes par un correcteur : état

Ouvert le 2026-11-24 à la demande de Xavier : « une maquette sur le site, avec les éléments affichés par page et des
exemples, le site web, le guide », « on doit pouvoir mettre des commentaires comme dans les maquettes ».
Une première version (2 186 textes extraits du code, page claude.ai) a été refusée : « beaucoup trop ».

## Suivi Origin

- Projet 44 (offreGen), tâche 622 « Relecture des textes par un correcteur ». Sous-tâches : 623 (page de relecture,
  conversation du 2026-11-24 rattachée), 624 (reporter les corrections), 625 (retirer la page).

## Ce qui est livré

- Une page de relecture : 36 écrans capturés, 385 textes numérotés T001 à T385. Un texte qui revient sur un autre
  écran n'est numéroté qu'une fois.
  - Le site (9 vues), la démo et son guide (11), le passage à l'essai et la mise en route (7), les courriels (9).
  - Trois colonnes par texte (demande de Xavier du 2026-11-25) : l'original, la version du correcteur (toujours
    modifiable, préremplie), les différences mot à mot (ajouts en vert, retraits en rouge barré, ␣ espace, ⍽ espace
    insécable ; Ctrl+Maj+Espace en pose une).
  - Aperçu en direct (demande de Xavier du 2026-11-25 : « il voit direct, le point il va bien, le point il va pas
    bien ») : à gauche, la copie figée de l'écran (HTML et styles de Dicta, sans scripts) dans un cadre. Chaque frappe
    y remplace le texte, surligné ; le texte en cours d'édition y est entouré et amené en vue. Les 385 textes y ont
    leur place ; un texte qui revient sur plusieurs écrans suit partout. La capture d'origine reste à un clic.
    Courriels : une ligne « Objet : … » en tête, comme dans une messagerie. Sous chaque écran, une
    remarque libre. Tout reste dans le navigateur du correcteur (localStorage) ; « Copier mes corrections »
    rassemble le tout pour un courriel, les insécables écrites « [insécable] ».
  - « Surligner les espaces insécables » montre où l'application en pose.
- Copies figées : `public/relecture-textes/ecrans/<écran>.txt` (le site ne sert pas de `.html` statique) et `.css`
  (les styles que les scripts de Dicta posent sur les éléments, bulles du guide comprises, convertis en règles : la
  CSP refuse les styles écrits dans le HTML). Le canevas de l'aperçu PDF devient une image. Le cadre hérite de la CSP
  de la page ; le test vérifie qu'aucune copie ne porte de script, de style en ligne ni de ressource d'un autre site.
- `public/index.php` : sous `php -S`, la liste des fichiers statiques reprend celle de `public/.htaccess` (`.txt`
  manquait, les copies répondaient 404 en local).
- Sur le site : route `/relecture` (`TextesARelireControleur`), qui n'existe que si le `.env` porte
  `RELECTURE_TEXTES=1`. Page `relecture/index.html`, fichiers dans `public/relecture-textes/` (styles, scripts, kit
  des maquettes recopié en entier, captures). Piège constaté au premier déploiement : avec un dossier
  `public/relecture`, Apache redirigeait `/relecture` vers `/relecture/` (404) avant PHP. Un test l'interdit. Conforme à la CSP fermée : aucun style ni script dans la page, les textes
  dans un bloc JSON que le navigateur n'exécute pas. Test : `tests/Http/TextesARelireTest.php`.
- En local : `MAQUETTE-textes.html` (kit en ligne, images lues dans `public/relecture-textes/img/`).

## Décisions de Claude, à valider

- Captures faites sur une copie locale de la branche (base `offregen_relecture`, messagerie en fichiers) : sur le test,
  entrer dans une démo envoie un courriel, redirigé chez Xavier. La page, elle, est servie par le test.
- Les textes viennent de l'écran affiché (DOM) au moment de la capture, données d'exemple comprises. Les courriels
  sont rendus par `Courriel::enHtml()` avec des données d'exemple ; trois d'entre eux sont recopiés de
  `src/Compte/LiensAcces.php` (lignes 65, 84, 224), leur construction lisant la base.
- La typographie du kit (`inject_kit.py`) ne touche pas les textes de l'application : ils sont dans le bloc JSON. Elle
  aurait corrigé en douce les espaces insécables manquants que le correcteur doit voir.

## Limites connues

- Étape 5 du guide : la dictée de la capture était muette, l'offre est vide ; la bulle et ses trois repères sont là.
- Les pages capturées en entier montrent la barre du bas collée au milieu de l'image (mise en route, étape 1).
- Hors périmètre choisi par Xavier : écrans de l'offre, factures, catalogue, réglages, pages juridiques, espace de
  l'équipe.

## Régénérer après des corrections

1. Copie de travail sur la tête de la branche, `.env.local` avec `DB_NOM=offregen_relecture`,
   `APP_URL=http://localhost:8010` et `RELECTURE_TEXTES=1`. Serveur :
   `PHP_CLI_SERVER_WORKERS=6 php -S 0.0.0.0:8010 -t public public/index.php` (sans les processus, l'écran d'attente
   de la dictée bloque le serveur).
2. `outils/captures/reinitialiser-base.sh` en root (`wsl -d Ubuntu -u root`) : base neuve et compte administrateur.
   Sans base neuve, le passage à l'essai refuse un courriel déjà pris.
3. `node outils/captures/tout.js` (`MK_PLAYWRIGHT`, `MK_CHROMIUM` : mémoire du projet). `tout.js courriels` refait
   les seuls courriels.
4. `python outils/maquette/construire.py //wsl.localhost/Ubuntu/home/xavie/<copie>` : écrit la page du site, ses
   fichiers, les copies des écrans et la maquette locale (sans aperçu en direct : un fichier ouvert du disque ne
   peut pas lire les copies).

## Vidéo de mode d'emploi

- 2026-11-26, à la demande de Xavier : 1 min 32, sans son, sous-titrée, enregistrée sur la page du test par
  `outils/video/video.js` (un correcteur fictif, « Marie Exemple » ; rien n'est écrit sur le serveur). Conversion :
  `ffmpeg -i <video>.webm -c:v libx264 -crf 24 -pix_fmt yuv420p -movflags +faststart -an relecture-dicta.mp4`.
- Fichier remis à Xavier hors du dépôt : `SynologyDrive/Travail/Xtradev/Projet/En cours/OffreGen/
  Relecture-Dicta-mode-d-emploi.mp4` (4,3 Mo). À refaire si la page change.

## Reste à faire

- Fait le 2026-11-24 avec l'accord de Xavier : déploiement sur le test, ligne `RELECTURE_TEXTES=1` ajoutée à
  `partage/.env` (avec un commentaire). Adresse : https://easy-offres.xtradev.ch/relecture
- Recevoir les corrections, les reporter dans les gabarits, `config/textes-onboarding.json` et `src/` (tâche 624).
- Retirer la route, `relecture/` et `public/relecture-textes/` quand la relecture est finie, et la ligne du `.env`
  du test (tâche 625).
