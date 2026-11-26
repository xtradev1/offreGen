# Revue du sous-chantier 31 (2026-11-24)

Trois relecteurs indépendants (Opus, lecture seule, sans phpunit) sur `6c33a7e` et `2ddda06` : exactitude, sécurité (OWASP), concurrence du verrou. Chaque trouvaille retenue a été relue dans le code avant correction. Détail des relecteurs : `scratchpad/revue-exactitude.md`, `revue-securite.md`, `revue-concurrence.md` (hors dépôt).

## Corrigées

| Id | Gravité | Trouvaille | Correction | Preuve |
|---|---|---|---|---|
| T1 | élevée | ffmpeg (900 s permis) et ffprobe tournent sans pouls : une conversion de plus de 130 s perd le verrou, le cron relance ffmpeg, la dictée finit en échec | `Commande::lancer` reçoit un rappel appelé au moins toutes les 5 s ; `PreparationAudio`, `DureeAudio`, `TranscripteurApi` et `Traiteur` y passent le pouls | `CommandePoulsTest` (rappel pendant `sleep 7`, commande arrêtée quand le pouls lève) |
| T2 | moyenne | `noterEchec` rend le statut même quand le verrou est à un autre : le courriel « dictée en échec » part d'un traitement qui a perdu la main | `noterEchec` rend null sans ligne touchée ; le courriel ne part que sur l'échec noté ici | `TraiteurTest::testUnEchecApresVerrouPerduNEnvoiePasDeCourriel`, vu rouge sans la correction |
| E1 | importante | une erreur levée après une étape « ok » (réponse coupée, lot sans numéro) ne laisse aucune trace ; l'attente qui suit tombe dans « hors étapes » | ligne « erreur » sans durée quand aucune étape ouverte ne porte l'erreur | `TraiteurTest::testUneErreurHorsEtapeLaisseSaTraceEtLAttenteSuit`, vu rouge sans la correction |
| E2 | importante | rejeu en échec : l'attente de la transcription reste « en cours » pour toujours | `echouerOuvertes($e, true)` dans le rejeu | `RejeuDicteeTest::testUnRejeuSansTexteFermeSonAttenteEnErreur`, vu rouge sans la correction |
| S2, T6, M6 | moyenne | rejeux sans plafond (un processus PHP chacun, 40 au plus) ; deux clics simultanés lancent deux rejeux ; blocage de 15 min plus court qu'un rejeu vivant (environ 35 min) | deux rejeux au plus sur le site ; contrôle et écriture sous un verrou nommé MariaDB ; blocage porté à 40 min | `RejeuDicteeTest::testPlafondDuSiteDureeEtJournal` |
| S3 | basse | un audio refusé pour sa durée part quand même en transcription payante au rejeu | refus quand `duree_ms` dépasse la durée permise | même test |
| T3 | faible | un pouls qui coupe juste après la conversion laisse le fichier converti dans /tmp | l'étape d'envoi s'ouvre dans le `try` qui nettoie | relu |
| T4, M5 | faible | le verrou perdu pendant l'attente du texte est pris pour une erreur passagère | relancé tel quel | relu |
| M1 | mineure | une étape interrompue compte 0 ms dans les colonnes et les statistiques | absente des colonnes, compte comme accroc | `RapportDicteesTest::testUneEtapeSansDureeLaisseLaColonneVide` |
| M3 | mineure | lecture de la dictée entre la prise du verrou et le `try` : une panne là laissait le verrou | lecture dans le `try` | relu |
| M7 | mineure | une étape finie s'écrivait en deux requêtes : un passage lisant entre les deux la prenait pour abandonnée | une seule requête | suites vertes |
| M8 | mineure | une durée lue sur une partie seulement écrasait celle du téléphone | durée gardée seulement quand toutes les parties sont lues | relu, non testé (DureeAudio lance un vrai ffprobe) |
| M9 | mineure | une reprise seule dans sa tentative disparaissait de la frise | bloc gardé | `RapportDicteesTest::testUneRepriseSeuleResteSurLaFrise` |
| S | basse | CSV : `fputcsv` ne neutralise pas les formules | apostrophe devant `= + - @` | `DicteesPageTest::testLeCsvNeutraliseLesFormules` |
| S1 (partie) | moyenne | les commentaires promettaient « jamais le texte » alors que le rapport l'affiche | commentaires corrigés ; le rejeu est noté au journal de l'équipe | relu |

## Ouvertes, pour Xavier

- **S1** : le rapport d'une dictée affiche le texte transcrit (original et rejeux), pour tous les comptes, à l'administrateur. C'est voulu pour comparer un rejeu (SPEC 4 bis), et conforme à ta demande du 2026-11-14 (« consulter les stats à tout moment et pouvoir rejouer les audio, textes »). Mais c'est le premier écran de l'administration qui montre le contenu d'une dictée, et la consultation n'est pas journalisée. À trancher : garder tel quel, journaliser chaque ouverture, ou cacher le texte derrière un clic journalisé.

## Acceptées sans correction

| Id | Trouvaille | Raison |
|---|---|---|
| S4 | la liste charge toute la période en mémoire (pas de `LIMIT`) | D2 : « accès à toutes, du moins durant la période de test » ; quelques centaines de dictées. À revoir avant la production (période par défaut). |
| S5 | `duree_ms` et `envoi_ms` viennent du téléphone, falsifiables | seulement les statistiques du compte lui-même ; aucun calcul métier ne s'en sert (grep du relecteur). |
| S6 | User-Agent brut gardé | il sert à affiner la lecture « Safari, iPhone » ; il vit et part avec la dictée. |
| T5 | écritures du chronomètre non gardées par le jeton | statistiques seulement ; le pouls, lui, est gardé. |
| M2 | un processus mort hors de toute étape ne laisse pas de reprise | fenêtre de quelques millisecondes entre deux étapes ; l'attente suivante le montre. |

Non vérifié par personne : la fréquence réelle d'`on_progress` et la limite de durée de PHP-FPM chez Infomaniak. À relever pendant la recette sur le test.
