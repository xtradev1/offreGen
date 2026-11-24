# Revue du 2026-11-24 : dictée annoncée à la réouverture, continuée à la suite

Trois relecteurs indépendants (Opus), sur le commit d60ffe1 : correction côté serveur, sécurité de l'import, comportement du téléphone. Surface sensible du profil : import de fichiers audio.

| # | Trouvaille | Lentille | Verdict | Suite |
|---|---|---|---|---|
| S1 | Une partie illisible par ffprobe faisait sauter le contrôle des 30 minutes pour toutes | sécurité | juste | corrigé : les parties lisibles se comptent ; test `testUnePartieIllisibleNeFaitPasSauterLaLimite` |
| S2 | Références notées à la fin : une panne au milieu relançait et refacturait les parties déjà parties | sécurité, serveur | juste | corrigé : référence notée dès le lancement, reprise à la première manquante ; test `testUnePanneAuMilieuNeRelancePasLesPartiesDejaParties` |
| S3 | 5 références de 100 caractères dépassent 255 | sécurité, serveur | juste | corrigé : VARCHAR(512) ; le `down` ne rétrécit plus la colonne |
| S4 | Verrou de 900 s pour cinq envois de 600 s au plus | serveur | juste, borne réelle | atténué par S2 : un second traiteur repart des références notées |
| S5 | Un lot perdu renvoie toutes les parties | serveur | juste | limite connue, rare, rien de perdu |
| S6 | Retour arrière : l'ancien code laisse des suites que rien ne voit | serveur | juste | corrigé pour la version suivante (`aPurger`, `fichiers()` lisent aussi les suites seules) ; offre partielle possible en retour arrière, limite connue |
| S7 | Début arrivé sans réponse puis continué : seconde offre | serveur, téléphone | juste | corrigé : « Continuer » réservé aux dictées coupées avant « Terminer » |
| T1 | Suite abandonnée remise « à plus tard » par le retour de l'historique | téléphone | juste (course) | corrigé : la fenêtre reste ouverte et repropose le début |
| T3 | Onglet en échec suspendu : la dictée cachée aux autres onglets | téléphone | juste | corrigé : verrou tenu pendant l'enregistrement et l'envoi seulement |
| T4 | Micro refusé depuis la reprise : dictée remise « à plus tard » | téléphone | juste | corrigé |
| T5 | Deux onglets : l'un continue, l'autre envoie | téléphone | juste, rare | corrigé : contrôle du verrou avant d'envoyer ou de continuer |
| T6 | Suite coupée avant sa première tranche : partie vide comptée | téléphone | juste | corrigé |
| T7 | Verrou d'une page gardée en mémoire (bfcache) sous WebKit | téléphone | non vérifié | sans objet après T3 : le verrou n'est tenu que pendant un enregistrement ou un envoi, que la page empêche de quitter |

Sans défaut selon les relecteurs : pas de traversée de chemin (la colonne JSON n'est écrite que par le serveur, `chemin()` refuse point et barre), `suite` mal formé refusé en 422 sans rien ranger, suites effacées par la purge, la remise à zéro et la suppression des démos, `getUserMedia` appelé dans le geste, numérotation cohérente après `retablir`.

À vérifier hors code : `post_max_size` du serveur (200 Mo par fichier, jusqu'à cinq fichiers) et le mode de facturation de la transcription d'Infomaniak (une dictée coupée en parties très courtes coûte-t-elle plus qu'une seule ?).
