# SPEC : de la voix à l'offre

Chantier ouvert le 2026-11-07. Tâche Origin 315. Réponses de Xavier du 2026-11-07 intégrées. Périmètre validé par Xavier le 2026-11-07 (« c'est bon »), production pilote ajoutée ensuite (point 16).

Hébergement : serveur Cloud Infomaniak. Site de test : `easy-offre.xtradev.ch` (Xavier, 2026-11-07 ; orthographe confirmée le 2026-11-14).

## Problème

Constaté dans le dépôt le 2026-11-07 : aucune ligne de PHP applicatif. La maquette v4
(`docs/plans/2026-11-06_maquette-ux/`) montre l'offre voulue, le cadrage
(`docs/plans/2026-11-05_mesures-ia/cadrage-produit.md`, section 5) fixe l'architecture, les scripts
du 2026-11-05 ont mesuré Whisper et la correction vocale en local. Rien ne relie un fichier audio à
une offre.

## Utilisateurs

| Rôle | Ce qu'il fait | Droits |
|---|---|---|
| Artisan | se connecte, importe un audio, lit et corrige ses offres, dicte des corrections | ses données seulement : catalogue, audios, offres |
| Compte démo | un artisan marqué `demo`, pour présenter offreGen et le faire tester (demande de Xavier) | comme un artisan, plus : catalogue fictif préchargé, données réinitialisables, quota d'IA plafonné, aucun courriel vers une autre adresse que la sienne |
| Xavier (administration) | crée les comptes artisan et démo, réinitialise un compte démo | par commandes en ligne (`bin/`), sans écran d'administration dans ce chantier |

Pas d'inscription libre dans ce chantier.

## Périmètre

Chaque ligne est vérifiable. Le découpage en lots se fait en phase 2.

1. **Squelette :** contrôleur frontal unique `public/index.php`, routes, pages Twig, couche d'accès aux données unique, migrations, PHPUnit et PHPStan qui tournent dans WSL.
2. **Connexion :** courriel et mot de passe, session, jeton CSRF sur chaque formulaire et chaque requête qui écrit. Toute route sans droit déclaré répond 403, et un test parcourt toutes les routes.
3. **Isolation :** chaque ligne métier porte l'identifiant de l'artisan. Un test prouve, route par route, que l'artisan A n'atteint ni l'audio, ni l'offre, ni le catalogue de B.
4. **Comptes démo :** commande de création, commande de réinitialisation (catalogue fictif des trois métiers de la maquette, offres effacées), plafond d'offres générées par jour, courriel limité à l'adresse du compte.
5. **Import d'un fichier audio** depuis le navigateur du téléphone :
   - type vérifié sur le contenu ;
   - taille plafonnée ;
   - fichier stocké hors de `public/` ;
   - formats visés : m4a, mp3, ogg, opus, wav, amr, 3gp (ajouté le 2026-11-07 : enregistreurs Android).
6. **Traitement, démarré tout de suite après l'import** (demande de Xavier) :
   - la page rend la main à l'artisan, puis la même requête lance la première étape ;
   - cron, chaque minute, reprend ce qui attend ou a échoué, vérifie et termine ;
   - chaque étape est enregistrée : `recu`, `transcription`, `transcrit`, `extrait`, `rapproche`, `pret` ou `echec` ;
   - un verrou empêche deux traitements du même audio ;
   - une étape rejouée ne double aucune ligne.
7. **Transcription :** Whisper chez Infomaniak (asynchrone). Un transcripteur factice lit un texte de référence, pour les tests et pour travailler sans jeton.
8. **Extraction et rapprochement :**
   - un appel au modèle transforme la transcription en lignes JSON, validées comme une saisie ;
   - le code cherche chaque ligne dans le catalogue de l'artisan et fait tous les calculs ;
   - un second appel, seulement si nécessaire, reçoit les lignes ambiguës avec 3 à 5 candidats ;
   - un modèle bon marché note chaque ligne ; sous le seuil, la ligne s'affiche « À vérifier ».
9. **L'offre à l'écran**, dans l'aspect de la maquette v4 :
   - liste des offres, écran d'une offre (lignes, total, « À vérifier ») ;
   - corrections à l'écran : quantité et unité, prix, suppression avec « Annuler », ajout depuis le catalogue, ligne libre.
10. **Commandes vocales :**
    - une courte dictée sur une offre ;
    - le modèle reçoit l'offre résumée et la commande, sans la transcription d'origine ni d'historique ;
    - il renvoie des appels d'outils, en liste blanche, que le code valide et applique ;
    - une commande floue ne modifie rien et pose une question ;
    - chaque outil a son équivalent à l'écran (point 9).
11. **Courriel « votre offre est prête ».** En développement, il est écrit dans un fichier, sans envoi.
12. **Banc de mesure :**
    - un jeu de visites (audio, plus le devis attendu en JSON) ;
    - un script qui mesure pour chaque visite les lignes justes, manquées et inventées, les quantités, les prix, le coût en CHF et le délai ;
    - il compare les modèles et les réglages ;
    - il tourne en local (faster-whisper, Ollama) et chez Infomaniak.
13. **Catalogue réel pour le banc :** un fichier CSV rempli par Xavier, chargé par un script. Sans le vrai catalogue, de vraies visites ne se rapprochent de rien.
14. **Site de test** chez Infomaniak, protégé par mot de passe, déployé depuis `main`. La liste « À prouver » de `docs/DEPLOIEMENT.md` y est déroulée.
15. **Enregistrement dans le navigateur**, une fois le site de test en HTTPS.
16. **Production pilote :** la même application, sur un environnement où Claude n'a aucun accès, pour les essais de Xavier avec de vraies données, puis ceux d'un artisan et des prospects. Préalables : double authentification, relecture de `docs/SECURITE.md` ligne à ligne, relecture par Fable, sauvegardes restaurées une fois. Ajouté parce que l'ordre des essais voulu par Xavier (Claude, puis Xavier, puis un artisan) amène de vraies données avant la fin du chantier.

## Hors périmètre

| Exclu | Raison |
|---|---|
| Aperçu du document client, envoi au client, PDF | Chantier suivant : le flux voix vers offre doit d'abord être juste. |
| Acomptes, factures, code QR suisse | Idem, et la facture de solde n'est pas cadrée (roadmap, risques). |
| Écran du catalogue et import de catalogue par l'interface | Chantier à part. Le banc se contente d'un CSV chargé par script (point 13). |
| Réglages de l'artisan (majoration ou ligne de supplément, TVA, arrondi) à l'écran | Valeurs par défaut en base. L'écran viendra avec l'envoi. |
| Double authentification | Lot suivant la connexion. Obligatoire avant la production. |
| Inscription libre, abonnement, paiement | Rien n'est décidé sur la vente. |
| Mode hors ligne, notification push | Décision du 2026-11-06 : ensuite. |
| Ouverture au public (inscription libre, vente) | Rien n'est décidé sur la vente. La production pilote (point 16) se limite aux comptes créés par Xavier. |

## Critères de succès

Observables, prouvés par une commande ou une capture collée dans `ETAT.md`.

- **Parcours complet :** sur le site de test, un compte importe depuis un téléphone un m4a de 30 minutes, reçoit le courriel et lit les lignes de l'offre.
- **Contrôles automatiques :** les tests passent dans WSL. Ils couvrent :
  - le parcours des routes, avec 403 par défaut ;
  - l'isolation de A contre B ;
  - un fichier texte renommé `.m4a`, refusé ;
  - le traitement coupé après la transcription puis repris par cron, sans ligne doublée ;
  - deux traitements lancés en même temps sur le même audio, dont un seul agit.
- **Commandes vocales :**
  - « mets 3 au lieu de 2 pour la paroi » modifie une seule ligne ;
  - une commande floue ne modifie rien et renvoie une question.
- **Comptes démo :**
  - une commande remet le compte à son état d'origine ;
  - le plafond quotidien bloque la génération suivante ;
  - un courriel vers une autre adresse est refusé.
- **Banc :** il rend un tableau par visite et par réglage. Les seuils à atteindre se fixent après la première mesure, avec Xavier.

## Contraintes

- `CODING_RULES.md`, `docs/SECURITE.md`, `docs/DEPLOIEMENT.md` opposables. PHP 8.5, composants Symfony 7.4 LTS, sans framework.
- Architecture : section 5 du cadrage. Deux appels au modèle par offre au plus, une seule relance. Le modèle ne calcule jamais un total. Le catalogue n'est jamais envoyé en entier.
- Aspect et ergonomie : maquette v4 validée, règle du pouce.
- La sortie du modèle et le texte dicté sont des données non fiables : ils ne donnent aucun droit.

### Ordre des données d'essai (Xavier, 2026-11-07)

1. **Claude crée les données de développement :** catalogue fictif, visites fictives écrites puis lues, transcriptions de référence. Elles servent jusqu'à ce que le banc soit fiable.
2. **Xavier enregistre ensuite ses propres visites,** puis teste l'application lui-même.
3. **Enfin, un artisan.**

Les étapes 2 et 3 sur l'application elle-même se font en production pilote (point 16), car le site de test reste un environnement où Claude a accès. L'application n'appelle qu'Infomaniak, jamais l'API d'Anthropic : ce que l'artisan dicte n'entre dans une conversation avec Claude que si quelqu'un l'y colle, ou si Claude lit la base, les fichiers ou les journaux d'un environnement qui le contient.

### Vraies données au banc (réponse 5 de Xavier : « entre A et B »)

Entorse à `docs/SECURITE.md` section 5 (« données réelles uniquement en production »), écrite au
roadmap. Conditions :

- **Accord :** chaque client dont la voix ou le logement figure dans un enregistrement a donné son accord (art. 179ter CP).
- **Stockage :**
  - les vraies visites vivent dans WSL, dans `~/offregen-banc/`, hors du dépôt et hors de SynologyDrive ;
  - jamais de commit, jamais dans une conversation ;
  - elles partent seulement vers Infomaniak, déjà prévu comme sous-traitant.
- **Ce que Claude lit :** jamais l'audio ni la transcription d'une vraie visite, car ce qui entre dans une conversation part chez Anthropic, aux États-Unis. Il lit seulement les sorties du banc : chiffres, et lignes d'offre avec leur écart au devis attendu. Pour déboguer une transcription, on prend une visite rejouée (dictée de mémoire, sans nom ni adresse).
- **Suppression :** à la fin du banc, ou au plus tard au premier artisan pilote. La date est écrite dans `ETAT.md`.

## Risques et points à trancher

1. **Démarrage immédiat :** il suppose que PHP tourne sous PHP-FPM chez Infomaniak (`fastcgi_finish_request`). C'est à prouver sur le site de test. Sinon, cron seul : une minute d'attente au plus.
2. **Whisper asynchrone :** son délai réel chez Infomaniak est inconnu. Pour une commande vocale de 5 secondes, une attente de 30 secondes rendrait la voix pénible. Le banc le mesure ; une transcription plus rapide pour les commandes est peut-être à prévoir.
3. **Comptes démo :**
   - les présentations faites par Xavier peuvent tourner sur le test ;
   - des prospects qui testent seuls pourraient y dicter de vraies données, interdites hors production.

   Recommandé : démo de Xavier sur le test, essais des prospects en production pilote. Retenu faute de réponse le 2026-11-07, à contredire.
4. **Conservation de l'audio :** 30 jours après que l'offre est prête, puis suppression automatique (Xavier, 2026-11-07).
5. **Taille maximale d'un import :** 200 Mo (Xavier, 2026-11-07). Les limites du serveur Cloud sont à relever au site de test.
6. **Formats :** la documentation d'Infomaniak (lue le 2026-11-12) refuse amr et 3gp et limite un fichier à 50 000 Ko. `PreparationAudio` convertit alors en Opus avec ffmpeg ; si ffmpeg manque sur l'hébergement, ces fichiers restent refusés. Constaté au site de test.
7. **Juridique :** accord du client à l'enregistrement, à faire confirmer par un juriste (roadmap, risques). L'application le rappelle à l'artisan avant l'import. Rappel retiré de l'écran le 2026-11-12 à la demande de Xavier (« c'est au client de faire ça ») : à porter dans les conditions d'utilisation acceptées par l'artisan.
8. **Taille du chantier :** seize points, découpés en neuf sous-chantiers (index dans `ETAT.md`). Le premier ne dépend ni du jeton ni du site de test.
9. **Modèle local en développement :** vérifié le 2026-11-12 dans la documentation, la route v2 d'Infomaniak est compatible OpenAI, avec outils. Ollama la remplace en local (sous-chantier 10) ; reste à le confirmer avec le jeton.
