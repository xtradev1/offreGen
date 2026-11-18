# Revue de la PR #3 avant la production

Étape 2 du `PLAN.md`, 18 novembre 2026. Six relecteurs indépendants, en lecture seule, une surface chacun : connexion et session, isolation entre artisans, fichiers, configuration et secrets, appels à l'IA (Opus) ; connexion et isolation en profondeur (Fable, à la demande de Xavier). Rapports complets dans le scratchpad de la session (`revue/*.md`), non versionnés.

**Verdict d'ensemble :** aucune fuite de données entre artisans, aucun accès sans connexion. Fable et Opus arrivent à la même conclusion par deux lectures séparées : toutes les requêtes de `src/Donnees` filtrent sur l'artisan, UPDATE et DELETE compris, et chaque identifiant reçu passe par un `parId` filtré. Les défauts graves portent sur le coût et la robustesse : la limite de 30 minutes contournable, un blocage de ffmpeg, une sortie du modèle coupée sans prévenir, le cookie de session sans `Secure`.

Verdicts : **confirmé** (vu dans le code ou mesuré par Claude), **écarté** (mesuré faux), **plausible** (lu, non reproduit). État : corrigé (avec le test), à corriger, ou reporté (avec la raison).

## Haute

| # | Défaut | Verdict | État |
|---|---|---|---|
| H1 | Limite de 30 minutes contournée : sur le site web d'Infomaniak, `proc_open` est coupée, la durée n'est pas lue à l'import (journal du test : 2 imports « durée illisible » les 15.11) et le traitement de fond ne la vérifie pas. Un fichier de 10 h passe, transcription payée en conséquence. | confirmé | corrigé : durée mesurée par les paquets dans le traitement de fond, avant tout appel payant ; trop long, échec immédiat (test) |
| H2 | `Commande::lancer` lit la sortie standard puis la sortie d'erreur, sans délai : un fichier piégé bloque ffmpeg pour toujours, le cron en relance un toutes les 15 minutes. | confirmé (code) | corrigé : les deux tubes lus en même temps, arrêt au bout du délai (test rouge avant, vert après) |
| H3 | Cookie de session sans `Secure` : `'auto'` est ignoré par Symfony hors framework ; `session.cookie_secure=0` sur le test (lu le 18.11). | confirmé | corrigé : Secure dès que l'adresse est en https, et par défaut (test) |
| H4 | Extraction sans `max_tokens`, `finish_reason` jamais lu : une réponse coupée donnait une offre vide marquée prête, sans « Réessayer ». | confirmé | corrigé (24 000 jetons de sortie au plus ; réponse coupée ou illisible = échec qu'on peut réessayer) |
| H5 | Quantité et prix dictés sans borne : 3 millions de m² cassaient l'insertion après l'appel payé. | confirmé | corrigé (bornes de la saisie à l'écran ; hors bornes, quantité 1 à vérifier, prix dicté ignoré) |

## Moyenne

| # | Défaut | Verdict | État |
|---|---|---|---|
| M1 | Au-delà de 300 lignes, les postes sont écartés sans rien dire (A13 de l'inventaire). | confirmé | corrigé (nombre gardé sur l'offre, avertissement à l'écran) |
| M2 | Identifiant de produit rendu en texte (`"12"`) : la ligne quittait le catalogue. | confirmé | corrigé |
| M3 | Blocage entretenu d'un artisan : chaque essai refusé pendant le blocage compte comme un échec et le prolonge. Quiconque connaît son courriel (il est sur ses offres) le tient dehors. | confirmé | corrigé : les essais refusés pendant le blocage ne sont plus notés (test) |
| M4 | Frein par adresse IP derrière un proxy : tous les artisans partageraient un compteur. | écarté (le journal du test enregistre les vraies adresses des visiteurs, 18.11) | |
| M5 | Connexion sans déconnexion : la session et le jeton CSRF d'avant survivent ; sur un appareil partagé, un message en attente passe au compte suivant. | confirmé | corrigé : session neuve à la connexion (test) |
| M6 | Session non révocable : changer le mot de passe ne coupe pas les autres sessions. | confirmé | à corriger avec le changement de mot de passe (étape 5, A5) |
| M7 | Plafond d'essai dépassé par deux imports simultanés. | plausible | reporté : il faut deux imports à la même seconde depuis deux sessions du même compte d'essai, pour une offre de trop au plus ; aucun test fiable sans deux connexions concurrentes |
| M8 | Pendant l'attente de la transcription, chaque erreur passagère compte une tentative ; cinq en 15 s mettent l'enregistrement en échec. | confirmé (code) | corrigé : une erreur passagère pendant l'attente ne compte pas ; le délai de 2 h borne l'attente (test) |
| M9 | La tentative ne se compte qu'après l'appel payé : un processus tué est repris toutes les 15 minutes sans fin. | confirmé (code) | corrigé : un verrou expiré sans avoir été rendu compte une tentative, la cinquième met en échec (tests) |
| M10 | Conversion ffmpeg sans durée ni taille maximales. | confirmé (code) | corrigé avec H1 : conversion bornée à la durée permise (test) |
| M11 | Purge : les enregistrements jamais prêts ni en échec gardent leur audio ; les copies temporaires de conversion ne sont jamais effacées ; « Réessayer » garde l'ancienne date de suppression. | plausible | à corriger avec la purge planifiée (étape 5, A9) |
| M12 | Mot de passe de l'utilisateur de structure dans le `.env` lu par le site web (contraire à `SECURITE.md`). | confirmé (code) | corrigé : `.env.structure`, lu par les migrations seules ; séparé sur le test le 2026-11-18 (test) |
| M13 | Aucun réglage `display_errors` dans `public/index.php` : une erreur de format du `.env` peut en montrer un morceau. | plausible | corrigé : `display_errors` coupé et erreur de démarrage rattrapée dans `public/index.php` (essai au navigateur local : 303, 200, 400) |
| M14 | Le contrôle du SQL hors de `src/Donnees` laisse passer `UPDATE t alias`, `DELETE alias FROM`, `REPLACE`, `TRUNCATE`. | confirmé (rejoué par le relecteur) | corrigé : alias, jointures, REPLACE, TRUNCATE (test des formes) |
| M15 | Le test d'isolation ne couvre ni les identifiants hors du chemin (`?offre=`, `?ajoutee=`, produit de B dans une facture), ni les pages de liste, ni le contenu des pages de A. | confirmé | corrigé : toutes les pages sans paramètre, le contenu des pages de A, `?offre=`, `?ajoutee=`, produit de B dans une facture (tests verts : aucune fuite) |
| M16 | Le catalogue entier part à chaque extraction, sans plafond : un très gros catalogue dépasse le contexte du modèle. | confirmé | reporté au sous-chantier 20 (extraction optimisée) ; d'ici là, plafond à fixer à l'import du catalogue |
| M17 | Comptes d'essai de vrais artisans sur le site de test, sans sauvegarde. | confirmé | décision de Xavier : Luigi ira en production |

## Basse

| # | Défaut | État |
|---|---|---|
| B1 | `X-HTTP-Method-Override` invalide : exception non rattrapée (erreur 500). | corrigé : 400 (test) |
| B2 | Délai d'inactivité sans durée maximale sur les appels à l'IA : un appel lent dépasse le verrou de 900 s. | corrigé : `max_duration` sur chaque appel (test) |
| B3 | Jetons du second appel (pièces) notés sous le mauvais modèle. | corrigé |
| B4 | Adresse `http://` acceptée pour l'IA : le jeton partirait en clair. | corrigé : https exigé en production (test) |
| B5 | `bin/traiter-file` : une exception arrête toute la passe. | corrigé |
| B6 | ffmpeg sans `-protocol_whitelist file`. | corrigé avec H1 : `-protocol_whitelist file` |
| B7 | Frein de swisstopo par session, pas par compte. | reporté (coût nul, service gratuit) |
| B8 | Un fichier de session par visite anonyme de `/connexion`, gardé 14 jours. | reporté (nettoyage par le ramasse-miettes de PHP) |
| B9 | Messages d'exception bruts au journal, sans rotation ni durée de conservation. | à corriger avec les alertes (étape 5, A10) |
| B10 | La file sert les 20 plus anciens enregistrements, tous artisans confondus. | reporté (équité, aucune fuite) |
| B11 | `.htaccess` : `.well-known` avant le refus des `.php` ; la sonde ne vérifie pas le cookie. | à vérifier sur le test |
| B12 | `bin/creer-compte` affiche le mot de passe. | voulu (à transmettre à l'artisan) ; remplacé par l'écran d'administration en production |

## Non vérifié

Réglages PHP du site web chez Infomaniak (`display_errors`, `disable_functions` exacte), version de ffmpeg en production, failles publiées pour dompdf 3.1.6, droits réels de l'utilisateur `3p81e5_app`, plafond de sortie par défaut et limites de débit d'Infomaniak.
