# Sous-chantier 30 : dictée mal comprise

Demande de Xavier, 2026-11-21 : « si la qualité est trop mauvaise […] il faut pouvoir soit nettoyer, soit afficher quelque chose », « faire écouter aux gens leur audio comme ça les gens ils se rendent compte que […] l'audio est vraiment pourri et puis que c'est pas la faute de l'application », « proposer dans ce cas-là de refaire l'audio », « que jamais il y ait le moindre problème, ça dise qu'il y a un problème plutôt que d'avoir une offre un peu pourrie », « de manière générale, je pense que la personne devrait pouvoir réécouter un peu son vocal ».

Ajout du même jour : « quand on est en Bluetooth, par exemple, bah, que ça met voilà, attention, vous êtes connecté à un appareil Bluetooth. Juste un petit message », « On pourrait analyser ce volume un peu en live. Et si on voit tout à coup que le volume n'est pas assez fort, de dire attention, le volume est peu fort ».

Décisions D1 à D8 de la maquette `MAQUETTE-dictee.html`, 2026-11-21, reportées en section 3.

## 1. Constat du 2026-11-21 (preuves dans ETAT.md)

Offre 61 du site de test (compte d'Aliette), 60 secondes d'audio. Infomaniak a rendu une seule phrase : « Il y a une personne qui s'est retrouvée bloquée et puis ça a bloqué tout le monde. » L'offre est sortie avec une ligne « Intervention pour personne bloquée » à 0 CHF, sans aucun avertissement.

Ce que l'audio contient vraiment :

| Passage | Qui parle | Volume moyen | Crête |
|---|---|---|---|
| 1,6 à 33 s | Aliette dicte la peinture du salon (10 m², 5 m², 4 m², parquet) | -40 à -42 dB | -19 à -25 dB |
| 33 à 40 s | une autre personne, à côté : « personne bloquée » | -34 dB | -14,6 dB |
| 40 à 60 s | Aliette, le plafond (25 m²) | -42 à -48 dB | -18 à -23 dB |
| témoin : dictée de Xavier, offre 59 | Xavier, téléphone en main | -24 dB | -1,6 dB |

La voix d'Aliette est 16 à 18 dB sous une dictée normale (6 à 8 fois moins forte), et environ 7 dB sous la voix d'à côté.

Ce que fait Whisper chez Infomaniak, mesuré en lui renvoyant le fichier :

- Le résultat est stable : deux envois du même fichier donnent le même texte, au caractère près.
- Le détail par segment (`verbose_json`) montre un seul segment, de 1,58 à 44,14 s, qui ne contient que la phrase de la voisine. Whisper a donc « couvert » 42 secondes de parole avec 17 mots. Il a pris la voix la plus forte pour la parole principale et la voix faible pour du bruit de fond. Rien n'est rendu après 44 s.
- Les 30 premières secondes envoyées seules, il entend Aliette : « OK. Je suis dans le salon. Je me repeins une fois quatre minutes. Et ensuite, je vais partir. » La dictée est donc bien dans le fichier, mais mal comprise (« quatre minutes » pour « 4 m² »), et elle disparaît dès que la voix plus forte entre dans la même fenêtre.
- Ni le format ni le volume ne sont en cause : le fichier remuxé avec sa durée donne le même texte. Le son normalisé (loudnorm) aussi, avec en plus une phrase inventée (« Sous-titrage FR ? »), hallucination classique de Whisper.
- Whisper large-v3-turbo en local entend davantage (« une fois 10 m², une fois 5 m², une fois 4 m² »), mais invente aussi des phrases. Ce n'est pas un remède sûr.

**La cause** : Aliette était sur le siège passager, le téléphone relié au Bluetooth de la voiture, dont le micro est près du conducteur. Sa voix arrivait faible, celle du conducteur plus forte. Whisper transcrit la voix dominante et traite la voix faible en bruit de fond : il a transcrit fidèlement le mauvais locuteur. Aucun réglage de l'envoi ne rattrape ça, le problème vient de l'enregistrement.

**Ce qui manque** : l'application ne compare pas ce qu'elle a compris à ce qu'elle a reçu. 60 secondes de son pour 17 mots, ça se voit.

Offre 60 (même compte, 85 s) : aucune dictée d'offre, une conversation de fond (parking, barrière). Même diagnostic, même absence d'alerte.

## 2. Le signal qui permet d'alerter

Whisper rend le texte compris ; il ne compte rien lui-même. Le serveur compte les mots de ce texte (les suites de lettres ou de chiffres séparées par des espaces) et les divise par la durée de l'enregistrement, que ffmpeg mesure déjà à la réception. Offre 61 : 17 mots pour 60 s, 0,28 mot par seconde. Une dictée normale en fait 1,5 à 3.

Le détail par segment (`response_format=verbose_json`, même appel, même prix) donne en plus, pour chaque morceau de texte, son début, sa fin et la probabilité que ce morceau ne contienne aucune parole (`no_speech`). Sur les enregistrements mesurés :

| Enregistrement | Mots par seconde | `no_speech` le plus haut | Verdict attendu |
|---|---|---|---|
| Xavier, offre 59 | 3,10 | 0,09 | bon |
| Démo génie civil, offre 64 (dictée lente) | 1,53 | 0,02 | bon |
| Aliette, offre 60 | 0,65 | 0,41 | douteux |
| Aliette, offre 61 | 0,28 | 0,48 | douteux |

La confiance moyenne du modèle (`avg_logprob`) est bonne même sur l'offre 61 (-0,18). Whisper est sûr de lui sur la phrase qu'il a gardée : ce chiffre ne sert pas d'alarme.

Le lot 1 (D7 validé) a mesuré les 22 enregistrements du test et trouvé une troisième dictée ratée, d'un autre genre : la dictée 14, voix nette, transcription tronquée par Infomaniak (55 mots compris sur 131). Voir ETAT.md, lot 1.

## 3. Comportement décidé (D1 à D8, 2026-11-21)

### Pendant l'enregistrement (dans le navigateur, sans appel au serveur)

1. **Jauge de la voix, lissée** (D2 : A, « il faut que la jauge soit une forme de moyenne sinon quand on respire ça devient pas assez fort »). Toujours visible. Elle montre le niveau de la voix sur les dernières secondes, en ne comptant que les instants où quelqu'un parle : une respiration ou une pause d'une seconde ne la fait ni baisser ni changer de couleur. Un trait marque le seuil sous lequel la compréhension baisse. Sous le seuil, elle passe à l'orange.
2. **Message « voix faible »** (D2 : A). Après 4 secondes de parole sous le seuil, un message apparaît sous la jauge ; il disparaît quand la voix revient. Texte :
   - titre « Voix trop faible » ;
   - corps « Rapprochez le téléphone de votre bouche ou éloignez-vous des autres voix. La qualité de la compréhension baisse. »
3. **Message Bluetooth, discret** (D1 : A, « il faut pas dire que c'est la voiture, dire juste que si un outil Bluetooth cela peut baisser la qualité, mais le point important c'est surtout le son »). Quand le micro utilisé semble être un appareil Bluetooth (nom du micro donné par le navigateur, ou fréquence de 16 000 Hz et moins), une ligne en haut de l'écran, l'enregistrement continue :
   - titre « Micro Bluetooth » ;
   - corps « Un appareil Bluetooth peut baisser la qualité. Suivez surtout la jauge. » ;
   - bouton « Prendre le micro du téléphone », affiché seulement si le navigateur sait changer de micro sans couper l'enregistrement.

   La jauge reste l'aide principale : un casque Bluetooth bien placé donne une voix nette, et la jauge le montre.

### À la réception (serveur)

4. **Mesure** : durée, volume de la voix (ffmpeg y est déjà).
5. **Transcription en `verbose_json`** : texte, segments, `no_speech` par segment.
6. **Verdict** par règles simples, calibrées au lot 1 : `bon`, `douteux` ou `inexploitable` (rien de compris). Seuils calibrés au lot 1 (2026-11-21, 22 enregistrements, tableau dans ETAT.md) : douteux si la voix (80e centile du niveau) est sous -32 dB, ou s'il y a moins de 1 mot par seconde de parole (secondes à -38 dB et plus ; seuil fixé par Xavier le 2026-11-21 : « quelqu'un qui parle lentement, c'est ok »), ou si un segment a un `no_speech` au-dessus de 0,6. Le verdict et ses chiffres sont gardés en base, pour recalibrer quand il y aura plus de dictées ratées que les trois du test.
7. **Relance coupée aux pauses** (Xavier, 2026-11-22 : « ok ça me va »). Si la voix est nette (80e centile à -32 dB et plus) et que le verdict est douteux, le serveur relance seul la transcription : l'audio coupé aux pauses (ffmpeg `silencedetect`, morceaux de 25 s au plus), les morceaux envoyés ensemble, les textes mis bout à bout. Il garde le texte le plus long des deux, puis juge à nouveau. Raison : Infomaniak applique un filtre de silence et ne rend pas de repères de temps, et Whisper saute alors des phrases dans les longs passages recollés (dictée 14 : 55 mots, 118 après la relance, 131 en local). Sur une voix faible, la relance ne sert à rien (dictée 19 : 28 mots) : la fenêtre s'ouvre directement. Coût mesuré : environ 0,01 ct par morceau, 3 à 4 s d'attente.

### Dictée douteuse ou inexploitable

8. **Fenêtre de choix, l'offre n'est créée qu'après** (D3 : B). Rien n'est envoyé au modèle d'extraction tant que l'artisan n'a pas choisi : l'offre fausse n'existe jamais, et l'extraction n'est payée que pour une offre voulue. La fenêtre contient :
   - le titre « Dictée difficile à comprendre » ;
   - ce qui a été mesuré, en mots simples : « 17 mots compris pour 1 minute d'enregistrement. Voix faible, une autre voix plus forte. Écoutez l'enregistrement : si le son est mauvais, refaites la dictée. » (la seconde phrase change selon la cause mesurée) ;
   - le lecteur, avec l'onde qui montre où la voix était faible, et le texte compris ;
   - « Refaire la dictée » (action principale) et « Créer l'offre quand même ».
9. **Si l'artisan quitte sans choisir**, la dictée reste dans la liste des offres avec un point orange (D6) ; un toucher rouvre la fenêtre. Elle suit la durée de conservation de l'audio (30 jours, `supprimer_apres`).
10. **« Créer l'offre quand même »** lance l'extraction comme aujourd'hui. L'offre garde un bandeau « Dictée difficile à comprendre » avec le lecteur et le texte compris, et le point orange dans la liste, jusqu'à ce que l'artisan touche « Garder ».

### Réécouter

11. **Le lecteur n'apparaît que sur les dictées douteuses** (D4 : B) : dans la fenêtre de choix, et sur une offre créée quand même. Une offre normale n'a pas de lecteur. Le fichier n'est servi qu'au compte qui l'a dicté.

### Refaire

12. **Depuis la fenêtre de choix** : la fenêtre de conseils (téléphone près de la bouche, à l'écart des autres voix, la jauge), puis l'enregistreur habituel. La dictée douteuse est abandonnée.
13. **Depuis une offre créée quand même** (D5 : A) : la nouvelle dictée remplace les lignes de l'offre, qui garde son numéro et son client.

### PC

14. **Trois colonnes** (D6 : Valider) : le bandeau au-dessus des lignes, le lecteur et le texte compris dans la colonne de droite, le point orange dans la liste.

### Ton des messages

15. **Textes moins familiers** (D6, « moins familier les textes ») : plus de « On a mal compris », « On vous entend mal ». Des constats neutres au vouvoiement, ceux des points 2, 3 et 8. Interprétation de Claude, à confirmer à la recette.

## 4. Hors champ

- **Nettoyer le son** : la normalisation a été mesurée, elle n'aide pas et ajoute une hallucination. Séparer deux voix demande un modèle lourd, hors de portée de l'hébergement. Écarté. À rouvrir seulement avec un banc qui prouve un gain.
- **Le lecteur sur toutes les offres** : écarté par D4. La réécoute générale demandée le 2026-11-21 (« la personne devrait pouvoir réécouter un peu son vocal ») n'est donc pas faite ici ; à rouvrir si Xavier change d'avis.
- Le compte commercial sans catalogue (ses lignes sortent à 0 CHF) : autre sujet, noté dans ETAT.md.
- La réécoute réservée à l'exploitant, et la relance de la transcription pour comparer : sous-chantier 23.

## 5. Critères d'acceptation

- Rejouée sur le test, la dictée de l'offre 54 sort complète après la relance (au moins 110 mots, l'escalier et le carrelage dans l'offre), sans fenêtre.
- Rejouée sur le test, la dictée de l'offre 61 ouvre la fenêtre de choix, sans offre créée et sans appel d'extraction (journal de l'application). La dictée de l'offre 59 donne une offre directement, sans fenêtre ni lecteur.
- Sur tous les enregistrements gardés sur le test, aucune dictée normale n'est marquée douteuse. Le tableau est collé dans ETAT.md.
- Jauge : en rejouant l'audio de l'offre 59 comme faux micro (Chromium, `--use-file-for-fake-audio-capture`), la jauge reste verte du début à la fin, pauses comprises ; avec l'audio de l'offre 61, le message « Voix trop faible » apparaît avant 10 s.
- Message Bluetooth : la détection est testée sur des noms de micros relevés (casque, kit mains libres, micro intégré) ; essai réel par Xavier dans sa voiture, sur iPhone.
- Le lecteur joue l'audio sur iPhone (Safari) et sur PC. Le fichier n'est servi qu'à son propriétaire (test d'accès d'un autre compte : 404).
- « Créer l'offre quand même » crée l'offre avec le bandeau ; « Refaire » depuis cette offre remplace les lignes sans perdre le numéro ni le client.
- Recette de Xavier par l'interface, sur le site de test.
