# REVUE : chantier sans rechargement (commit bf9b5ab, puis corrections)

Revue de phase 7, le 2026-11-23. Quatre relecteurs indépendants sur Opus, une lentille chacun : serveur (A), navigateur (B), régressions et promesses (C), sécurité (D). Rapports complets hors dépôt (scratchpad de la session) ; ce qui suit en reprend chaque trouvaille bloquante ou importante, avec ce qui en a été fait et la preuve. Les mineures retenues sont groupées à la fin.

## Bloquantes

| Trouvaille | Verdict | Correction | Preuve |
|---|---|---|---|
| A-B1, C-I1, D-I4 : une clé d'envoi restée vide (processus tué, erreur fatale) bloquait toute la file de l'artisan 30 jours, bandeau « hors réseau » | juste | `DepotEnvois::deja` : une réservation sans adresse de plus de 3 minutes est libérée, le geste repart ; `noter` raté ne casse plus la réponse | `EnvoiCleTest::testUneReservationAbandonneeLaissePartirLeGeste` |
| B-B1 : supprimer une ligne qui a des variantes faisait paraître la variante deux fois (trois après « Annuler ») : le serveur la rend en option sous le même id | juste | la corbeille d'une ligne à variantes redemande toute la liste ; « Annuler » d'une ligne aussi | recette par l'interface : 1 exemplaire après la corbeille, dans les options ; 1 après « Annuler », dans les lignes |
| C-B1 : la carte des conditions et le formulaire de sa fenêtre portaient l'id `conditions` : « Enregistrer » ne faisait rien | juste (trouvé aussi par le banc) | carte renommée `carte-conditions` | `MorceauxTest::testAucuneFenetreNeReprendUnIdDeLOffre`, rouge avec l'ancien id, vert avec le nouveau |
| C-B2 : « Créer la facture » passait par la file ; sans réseau, la facture aurait été créée plus tard, en silence, peut-être deux fois | juste | les formulaires `data-sortir` (acte qui mène ailleurs) n'entrent jamais dans la file : sans réseau, rien n'est fait et la fenêtre le dit | recette : 0 geste en file, « Pas de connexion au serveur : rien n'est enregistré », fenêtre ouverte, aucune facture en base |

## Importantes

| Trouvaille | Verdict | Correction | Preuve |
|---|---|---|---|
| A-I1 : un geste rejoué (réponse perdue) perdait ses messages, refus compris | juste | les messages de l'action sont rangés avec sa clé et rendus au rejeu | `EnvoiCleTest::testUnGesteRejoueRetrouveSesMessages` |
| A-I2, B-I2 : à la fermeture de la page, tous les gestes en attente partaient en même temps, dans un ordre non garanti | juste | plus d'envoi groupé : le geste en vol finit seul, les autres repartent de la page suivante, dans l'ordre | lecture, recette hors réseau verte |
| A-M6 : la relecture rendait toute la page à chaque « C'est juste », pour rien | juste | aucun morceau demandé : la page n'est pas rendue, ses messages restent pour la page suivante | `MorceauxTest::testSansMorceauDemandeLesMessagesRestentPourLaPageSuivante` ; 0,6 Ko par « C'est juste » |
| A-M1 : relire des morceaux d'une page qui redirige retirait des éléments de l'écran | juste | un GET qui redirige mène à l'autre écran | `MorceauxTest::testUneRelectureDeMorceauxQuiRedirigeMeneAILAutreEcran` |
| D-I1 : un geste fait dans la page d'un compte pouvait partir dans la session d'un autre (même navigateur, deux artisans) | juste | chaque geste porte son compte (`X-Compte`) ; le serveur refuse un autre compte (409), le téléphone garde le geste ; le jeton neuf n'est pris que d'une page du même compte | `EnvoiCleTest::testUnGesteDUnAutreCompteEstRefuseSansRienFaire` |
| D-I2, B-I9 : le service worker pouvait montrer les pages gardées d'un compte à un autre (lien de démo, réseau lent) | juste | copies lues sous le seul compte noté ; toute entrée de session (connexion, lien de démo, mot de passe, retour à son espace) les efface ; la règle des pages jamais gardées reconnaît enfin `/d/…` | lecture ; recette hors réseau verte |
| D-I3, C-I4 : données de clients gardées sans session et sans limite ; PDF passés en `no-cache` | en partie, décision de Xavier | copies et PDF gardés 14 jours au plus (durée d'une session), effacés à la déconnexion et à toute entrée de session ; PDF de nouveau en `no-store` pour le navigateur (le service worker les garde pour le compte) | `PdfGardeTest` ; reste à Xavier : la mention dans la politique de confidentialité |
| B-I1 : corriger une quantité puis toucher la corbeille faisait revivre la ligne supprimée | juste | une ligne retirée au toucher reste cachée, quelle que soit la réponse qui arrive ensuite | recette : ligne cachée après les deux réponses, supprimée en base |
| B-I4 : un geste d'abord dit « en attente » ne disait jamais son refus | juste | son refus se dit toujours | lecture |
| B-I5 : dans la relecture, deux touchers rapides validaient une ligne que l'artisan n'avait pas vue | juste | 700 ms de lecture après chaque ligne, bouton bloqué | recette : la deuxième ligne reste à relire, à l'écran |
| B-I6 : hors réseau, « Envoyer » partageait le PDF gardé, sans les changements en attente | juste | « Envoyer » le dit et attend ; la file partie, l'aperçu se refait | lecture |
| B-I7 : une page gardée ne montrait pas les gestes en attente : l'artisan risquait de refaire un ajout | juste | le bandeau nomme le geste en attente ; la ligne provisoire et la valeur tapée se remontrent sur leur écran | recette : « 3 changements attendent (« Bâche de protection » ajoutée) » |
| B-I8 : réseau lent, page gardée marquée « hors réseau », jamais proposée à jour | juste | « réseau lent » distingué ; la page à jour arrivée, le service worker le dit et la page la propose | lecture |
| B-I10 : ajout au PC, le curseur sautait dans la quantité pendant la frappe du produit suivant | juste | pas de saut tant que l'artisan tape | lecture |
| B-I11, C-I2 : les fichiers sans version étaient servis du cache pour toujours (ex. `texte-pdf.js`) | juste | seul un fichier versionné (ou PDF.js) est servi du cache d'abord ; les autres suivent le réseau, la copie ne sert que sans réseau | lecture |
| C-I3 : le bandeau du réseau faisait sauter la page et couvrait la barre du haut | juste | bandeau posé au-dessus des boutons du bas, sans décaler l'écran ; « Envoi… » seulement si l'envoi dure | recette visuelle (captures du comparatif) |
| C : trois assertions de la relecture passaient à vide (tout est dans la page) | juste | assertions refaites sur l'attribut `hidden` ; test des lignes toutes dans la page | `RelectureTest` |
| C : aucun test 422 ni 409 par morceaux | juste | ajouté | `MorceauxTest::testUnRefusDeFormulaireEtUnConflitPassentTelsQuels` |
| Régression trouvée en corrigeant C-B2 : les cases du choix du client dans « Facturer » auraient fait quitter l'offre | juste | une fenêtre suit son action dans la fenêtre, l'écran derrière relit ses morceaux | recette : on reste sur l'offre, la fenêtre se recharge, la case a changé |

## Mineures retenues

- Adresses avec un caractère de contrôle refusées (`Morceaux::local`, test) ; ETag suffixé « -gzip » par Apache reconnu (test) ; fichiers provisoires de `PdfGarde` effacés en cas d'échec ; chemin du cache des PDF corrigé dans les commentaires.
- Bandeaux « Annuler » déjà touchés jamais reposés ; anciens bandeaux d'une page gardée retirés ; dernière carte archivée : la liste revient avec son message ; tri gardé après une remise depuis les archives ; titre d'une catégorie vidée retiré.
- Gestes gardés plus d'une semaine : ils ne partent plus, l'écran le dit (le serveur oublie les clés après 30 jours).
- Glissé d'une ligne qui avalait le clic suivant ; préchargements de « + Ligne » qui s'empilaient sans réseau ; feuille de couleur gardée sous sa vraie adresse ; page « hors réseau » avec sa CSP ; adresses du préchargement vérifiées ; `srcset` et images des modèles versionnés ; boutons bloqués pendant l'envoi sur les écrans de l'équipe.

## Écartées ou reportées, avec la raison

- D-M5 : croissance de la table `envoi` (bornée par la purge de 30 jours au fil des envois de l'artisan) ; D-M7 : deux rendus par geste (le coût du contrat, un seul aller-retour) ; A-M3, D-M9 : clé liée ni à l'adresse ni au corps (le téléphone en tire une par geste).
- B-I3 : le dernier chiffre tapé au moment même de quitter la page, pendant qu'un autre geste part, ne survit que si l'écriture dans IndexedDB aboutit : non vérifié selon les navigateurs, accepté.
- A-M2 : une page cible qui tombe en erreur consomme les messages de l'action : cas d'erreur du serveur, accepté.
- C : la version du catalogue (`data-catalogue`) promise au plan n'est pas faite : la fenêtre « + Ligne » se refait en arrière-plan après chaque usage, ce qui la tient à jour pour la fois suivante (une requête de fond après « Terminé »). Le rattachement et la fiche produit ne sont pas préchargés. Un client enregistré fait revenir la liste entière (rangée par nom). Les images en bloc `verbatim` de la page de présentation restent sans version (7 jours).
- Les dictées à l'abri ne s'effacent pas à la déconnexion : elles partent quand leur offre est prête (sous-chantier 27) ; `Clear-Site-Data` « storage » les aurait effacées.
