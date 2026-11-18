# Revue de la PR #3 avant la production

Étape 2 du `PLAN.md`, 18 novembre 2026. Six relecteurs indépendants, en lecture seule, une surface chacun : connexion et session, isolation entre artisans, fichiers, configuration et secrets, appels à l'IA (Opus) ; connexion et isolation en profondeur (Fable, à la demande de Xavier). Rapports complets dans le scratchpad de la session (`revue/*.md`), non versionnés.

**Verdict d'ensemble :** aucune fuite de données entre artisans, aucun accès sans connexion. Fable et Opus arrivent à la même conclusion par deux lectures séparées : toutes les requêtes de `src/Donnees` filtrent sur l'artisan, UPDATE et DELETE compris, et chaque identifiant reçu passe par un `parId` filtré. Les défauts graves portent sur le coût et la robustesse : la limite de 30 minutes contournable, un blocage de ffmpeg, une sortie du modèle coupée sans prévenir, le cookie de session sans `Secure`.

Verdicts : **confirmé** (vu dans le code ou mesuré par Claude), **écarté** (mesuré faux), **plausible** (lu, non reproduit). État : corrigé (avec le test), à corriger, ou reporté (avec la raison).

## Haute

| # | Défaut | Verdict | État |
|---|---|---|---|
| H1 | Limite de 30 minutes contournée : sur le site web d'Infomaniak, `proc_open` est coupée, la durée n'est pas lue à l'import (journal du test : 2 imports « durée illisible » les 15.11) et le traitement de fond ne la vérifie pas. Un fichier de 10 h passe, transcription payée en conséquence. | confirmé | à corriger : vérification dans le traitement (ligne de commande, `proc_open` permise), conversion bornée à la durée maximale |
| H2 | `Commande::lancer` lit la sortie standard puis la sortie d'erreur, sans délai : un fichier piégé bloque ffmpeg pour toujours, le cron en relance un toutes les 15 minutes. | confirmé (code) | à corriger : lecture des deux tubes en même temps, délai d'arrêt |
| H3 | Cookie de session sans `Secure` : `'auto'` est ignoré par Symfony hors framework ; `session.cookie_secure=0` sur le test (lu le 18.11). | confirmé | à corriger |
| H4 | Extraction sans `max_tokens`, `finish_reason` jamais lu : une réponse coupée donnait une offre vide marquée prête, sans « Réessayer ». | confirmé | corrigé (24 000 jetons de sortie au plus ; réponse coupée ou illisible = échec qu'on peut réessayer) |
| H5 | Quantité et prix dictés sans borne : 3 millions de m² cassaient l'insertion après l'appel payé. | confirmé | corrigé (bornes de la saisie à l'écran ; hors bornes, quantité 1 à vérifier, prix dicté ignoré) |

## Moyenne

| # | Défaut | Verdict | État |
|---|---|---|---|
| M1 | Au-delà de 300 lignes, les postes sont écartés sans rien dire (A13 de l'inventaire). | confirmé | corrigé (nombre gardé sur l'offre, avertissement à l'écran) |
| M2 | Identifiant de produit rendu en texte (`"12"`) : la ligne quittait le catalogue. | confirmé | corrigé |
| M3 | Blocage entretenu d'un artisan : chaque essai refusé pendant le blocage compte comme un échec et le prolonge. Quiconque connaît son courriel (il est sur ses offres) le tient dehors. | confirmé | à corriger |
| M4 | Frein par adresse IP derrière un proxy : tous les artisans partageraient un compteur. | écarté (le journal du test enregistre les vraies adresses des visiteurs, 18.11) | |
| M5 | Connexion sans déconnexion : la session et le jeton CSRF d'avant survivent ; sur un appareil partagé, un message en attente passe au compte suivant. | confirmé | à corriger (session neuve à la connexion) |
| M6 | Session non révocable : changer le mot de passe ne coupe pas les autres sessions. | confirmé | à corriger avec le changement de mot de passe (étape 5, A5) |
| M7 | Plafond d'essai dépassé par deux imports simultanés. | plausible | à corriger (verrou sur le compte pendant le contrôle) |
| M8 | Pendant l'attente de la transcription, chaque erreur passagère compte une tentative ; cinq en 15 s mettent l'enregistrement en échec. | confirmé (code) | à corriger |
| M9 | La tentative ne se compte qu'après l'appel payé : un processus tué est repris toutes les 15 minutes sans fin. | confirmé (code) | à corriger |
| M10 | Conversion ffmpeg sans durée ni taille maximales. | confirmé (code) | à corriger avec H1 |
| M11 | Purge : les enregistrements jamais prêts ni en échec gardent leur audio ; les copies temporaires de conversion ne sont jamais effacées ; « Réessayer » garde l'ancienne date de suppression. | plausible | à corriger avec la purge planifiée (étape 5, A9) |
| M12 | Mot de passe de l'utilisateur de structure dans le `.env` lu par le site web (contraire à `SECURITE.md`). | confirmé (code) | à corriger : fichier à part, lu par les migrations seules |
| M13 | Aucun réglage `display_errors` dans `public/index.php` : une erreur de format du `.env` peut en montrer un morceau. | plausible | à corriger |
| M14 | Le contrôle du SQL hors de `src/Donnees` laisse passer `UPDATE t alias`, `DELETE alias FROM`, `REPLACE`, `TRUNCATE`. | confirmé (rejoué par le relecteur) | à corriger |
| M15 | Le test d'isolation ne couvre ni les identifiants hors du chemin (`?offre=`, `?ajoutee=`, produit de B dans une facture), ni les pages de liste, ni le contenu des pages de A. | confirmé | à corriger |
| M16 | Le catalogue entier part à chaque extraction, sans plafond : un très gros catalogue dépasse le contexte du modèle. | confirmé | reporté au sous-chantier 20 (extraction optimisée) ; d'ici là, plafond à fixer à l'import du catalogue |
| M17 | Comptes d'essai de vrais artisans sur le site de test, sans sauvegarde. | confirmé | décision de Xavier : Luigi ira en production |

## Basse

| # | Défaut | État |
|---|---|---|
| B1 | `X-HTTP-Method-Override` invalide : exception non rattrapée (erreur 500). | à corriger |
| B2 | Délai d'inactivité sans durée maximale sur les appels à l'IA : un appel lent dépasse le verrou de 900 s. | à corriger |
| B3 | Jetons du second appel (pièces) notés sous le mauvais modèle. | à corriger |
| B4 | Adresse `http://` acceptée pour l'IA : le jeton partirait en clair. | à corriger (refus hors développement) |
| B5 | `bin/traiter-file` : une exception arrête toute la passe. | à corriger |
| B6 | ffmpeg sans `-protocol_whitelist file`. | à corriger avec H1 |
| B7 | Frein de swisstopo par session, pas par compte. | reporté (coût nul, service gratuit) |
| B8 | Un fichier de session par visite anonyme de `/connexion`, gardé 14 jours. | reporté (nettoyage par le ramasse-miettes de PHP) |
| B9 | Messages d'exception bruts au journal, sans rotation ni durée de conservation. | à corriger avec les alertes (étape 5, A10) |
| B10 | La file sert les 20 plus anciens enregistrements, tous artisans confondus. | reporté (équité, aucune fuite) |
| B11 | `.htaccess` : `.well-known` avant le refus des `.php` ; la sonde ne vérifie pas le cookie. | à vérifier sur le test |
| B12 | `bin/creer-compte` affiche le mot de passe. | voulu (à transmettre à l'artisan) ; remplacé par l'écran d'administration en production |

## Non vérifié

Réglages PHP du site web chez Infomaniak (`display_errors`, `disable_functions` exacte), version de ffmpeg en production, failles publiées pour dompdf 3.1.6, droits réels de l'utilisateur `3p81e5_app`, plafond de sortie par défaut et limites de débit d'Infomaniak.
