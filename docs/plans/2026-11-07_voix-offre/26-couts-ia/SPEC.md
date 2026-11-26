# Sous-chantier 26 : partie administration, coût de l'IA et rentabilité

Demandes de Xavier du 2026-11-17 : « c'est important pour les statistiques que j'aie le coût global des requêtes API »,
« juste pour moi, comme statistiques d'exploitation », puis « un écran admin vraiment séparé », « une partie dédiée »,
avec les statistiques et, plus tard, les abonnements et la marge.

## Ce qu'on sait (vérifié le 2026-11-17)

- **Qwen (extraction)** : chaque réponse rend ses jetons (`usage`). Le coût facturé par Infomaniak égale exactement
  jetons × tarif publié (16.11 : 1208 et 192 jetons, CHF 0.0010592 des deux côtés ; banc du 17.11, idem).
- **Whisper (transcription)** : facturé au temps de calcul du serveur (Xavier), pas à la durée de l'audio (dictée du
  16.11 : 19,2 s d'audio, 1 s facturée).
- **Spécification officielle de l'API** (`https://developer.infomaniak.com/openapi.json`, lue le 17.11) : aucun point
  d'entrée ne rend le coût d'une requête. La transcription est asynchrone ; son résultat rend l'état, l'adresse, le nom
  et la taille du fichier, le texte, ni durée ni coût. `GET /1/ai/{produit}/consumptions` rend un total par jour et par
  modèle (requêtes, coût, jetons ou temps de calcul), sans filtre de date (pagination et tri seulement).
- Conséquence : Qwen se compte exactement par offre ; Whisper se répartit au prorata de l'audio (estimation) ; le total
  facturé vient du relevé quotidien. Un produit IA séparé pour la production (nouveau jeton, Xavier) rend ce total exact.

## Ce que l'outil fait

1. **Chaque appel à l'IA est noté** (une ligne par appel, sans aucun texte de la dictée) : artisan, offre, sorte
   (extraction ou transcription), modèle, jetons envoyés et reçus, durée de l'audio, nombre de lignes de l'offre,
   nombre de produits du catalogue à ce moment, coût (exact pour Qwen ; pour Whisper, la part du jour au prorata de
   l'audio, posée par le relevé).
2. **Relevé quotidien de la consommation Infomaniak** (tâche planifiée) : les jours récents réécrits à chaque passage.
3. **Une partie administration séparée** (`/admin`, sa propre mise en page et son propre menu), ouverte au seul droit
   administrateur :
   - **Tableau de bord** : la période choisie en un coup d'œil (offres, coût de l'IA, coût moyen par offre) ; plus tard
     les abonnements et la marge.
   - **Coût de l'IA** : par modèle (relevé), l'écart avec nos chiffres et son explication.
   - **Artisans** : les 10 ou 20 plus gros, triés au choix par nombre d'offres, coût total ou coût moyen par offre ;
     recherche d'un artisan par son nom. Jamais la liste entière (objectif de Xavier : 1000 artisans).
   - **Offres** : analyse anonyme, sans client ni texte : coût selon la taille du catalogue, le nombre de lignes, la
     durée de l'audio ; coût par ligne, par produit du catalogue ; les offres les plus chères, anonymes.
   - **Rentabilité** (plus tard, déjà dans la maquette) : abonnements encaissés, frais, coût de l'IA, résultat ; le mois
     en cours en direct et sa projection de fin de mois.
4. **Période** : le mois par défaut, l'année, ou deux dates (du… au…), avec des raccourcis (ce mois, le mois passé,
   cette année, les 14 derniers jours).

## Hors périmètre

- Aucun écran pour l'artisan.
- Les abonnements n'existent pas encore : la rentabilité est maquettée, branchée quand ils existeront.
- Ni facturation aux artisans, ni plafond de dépense (le plafond quotidien d'enregistrements existe déjà).

## Décisions

- **F1, accès (Xavier)** : un droit administrateur sur le compte, refusé par défaut, que Xavier pourra donner à un
  collaborateur. `/admin` répond 404 à tout autre compte ; son entrée n'apparaît que pour un administrateur. Le droit
  se donne et se retire par commande (`bin/droit-admin <courriel> oui|non`) ; un écran pour le donner viendra avec le
  premier collaborateur.
- **F2, Whisper (Xavier)** : réparti entre les offres du jour au prorata de leur audio ; une estimation, à affiner avec
  les données.
- **F3, relevé (Claude)** : chaque nuit, les 7 derniers jours réécrits ; deux passages ne doublent rien.
- **F4, tarifs (Claude)** : dans le code, datés ; le relevé reste la vérité, l'écart se voit.
- **Écran du coût validé par Xavier** (2026-11-17), téléphone compris ; colonnes « jetons envoyés », « jetons reçus »,
  « temps de calcul ».
- **Partie administration séparée, période libre, classements, analyse anonyme, rentabilité** : sur la maquette
  `MAQUETTE-admin.html` (G1 à G5).

## Critères de succès

- Une extraction faite ce jour apparaît avec ses jetons et un coût égal au relevé d'Infomaniak du même jour, au
  dix-millième de franc près, quand elle est seule ce jour-là.
- Un compte sans droit administrateur qui demande `/admin` reçoit 404, quelle que soit la page.
- Le relevé passé deux fois le même jour ne double rien.
- Avec 1000 artisans et 50 000 appels notés, chaque page de l'administration répond en moins d'une seconde.
- Aucune page de l'administration n'affiche un texte de dictée, un nom de client ou une ligne d'offre.
