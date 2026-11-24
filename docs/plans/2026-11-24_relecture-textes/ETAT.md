# Relecture des textes par un correcteur : état

Ouvert le 2026-11-24 à la demande de Xavier : « une maquette sur le site, avec les éléments affichés par page et des
exemples, le site web, le guide », « on doit pouvoir mettre des commentaires comme dans les maquettes ».
Une première version (2 186 textes extraits du code, page claude.ai) a été refusée : « beaucoup trop ».

## Ce qui est livré

- Une page de relecture : 36 écrans capturés, 389 textes numérotés T001 à T389. Un texte qui revient sur un autre
  écran n'est numéroté qu'une fois.
  - Le site (9 vues), la démo et son guide (11), le passage à l'essai et la mise en route (7), les courriels (9).
  - Sous chaque texte, « Corriger » ouvre un champ prérempli ; sous chaque écran, une remarque libre. Tout reste dans
    le navigateur du correcteur (localStorage) ; « Copier mes corrections » rassemble le tout pour un courriel.
  - « Surligner les espaces insécables » montre où l'application en pose.
- Sur le site : route `/relecture` (`TextesARelireControleur`), qui n'existe que si le `.env` porte
  `RELECTURE_TEXTES=1`. Page `relecture/index.html`, fichiers dans `public/relecture/` (styles, scripts, kit des
  maquettes recopié en entier, captures). Conforme à la CSP fermée : aucun style ni script dans la page, les textes
  dans un bloc JSON que le navigateur n'exécute pas. Test : `tests/Http/TextesARelireTest.php`.
- En local : `MAQUETTE-textes.html` (kit en ligne, images lues dans `public/relecture/img/`).

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

1. Copie de travail sur la tête de la branche, `.env.local` avec `DB_NOM=offregen_relecture` et
   `APP_URL=http://localhost:8010`, serveur `PHP_CLI_SERVER_WORKERS=6 php -S 0.0.0.0:8010 -t public public/index.php`
   (sans les processus, l'écran d'attente de la dictée bloque le serveur).
2. `outils/captures/` : `node parcours.js`, puis `reprise.js` (étape 5 et avant l'envoi), `site.js`, `courriels.js`
   (`MK_PLAYWRIGHT`, `MK_CHROMIUM` : mémoire du projet).
3. `python outils/maquette/construire.py //wsl.localhost/Ubuntu/home/xavie/<copie>` : écrit la page du site, ses
   fichiers et la maquette locale.

## Reste à faire

- Déployer sur le test et ajouter `RELECTURE_TEXTES=1` à `partage/.env` (accord de Xavier à demander).
- Recevoir les corrections, les reporter dans les gabarits, `config/textes-onboarding.json` et `src/`.
- Retirer la route et `public/relecture/` quand la relecture est finie, ou retirer la ligne du `.env`.
