# Revue : parcours de la dictée et constats des parcours

Revue du 2026-11-26. Intensité : un relecteur adversarial (Opus, lecture seule). Raison : le diff ne touche aucune
surface sensible du profil (ni droits, ni connexion, ni accès aux données, ni import côté serveur, ni courriels). Seul
ajout côté données : le JSON de `/enregistrements/{uuid}/etat` porte l'objet, le client et le nombre de lignes de l'offre,
lus dans la portée de l'artisan (route couverte par `IsolationRoutesTest`).

Le relecteur a reçu des chemins, pas mes conclusions. Chaque trouvaille a été relue dans le code avant son verdict.
Les verdicts sont de Claude.

| Trouvaille | Gravité | Verdict | Suite |
|---|---|---|---|
| I1. Le filtre d'une liste gardait la liste trouvée au chargement ; revenue en morceau, la nouvelle n'était plus filtrée (recherche de Mes offres) | important | retenu | `brancherFiltre` relit sa liste à chaque frappe. Prouvé par Playwright : liste relue en morceau, la frappe suivante filtre la nouvelle (1 sur 4). |
| I2. Le guide de la démo finit sur la page de l'offre ; l'arrivée directe sur la relecture, le client ou le PDF lui faisait manquer ses dernières étapes | important | retenu | Tant que le guide se montre (`guideAMontrer`), la dictée mène à l'offre comme avant. Test `testTantQueLeGuideSeMontreLaDicteeMeneALOffre` vu rouge. Guide rejoué par Playwright de l'étape 1 à l'étape 5. |
| I2 bis (trouvé en rejouant le guide). La bulle de l'attente sortait de l'écran : sa cible occupe maintenant tout l'écran | important | retenu | La bulle vise la carte des étapes et se pose dessous ; `guide.js` ne pose plus une bulle sous le bas de l'écran. Vu à 390 et à 320 px. |
| I3. Une offre sans ligne et au client reconnu arrivait sur un PDF vide | important | retenu | `Arrivee::adresse` prend le nombre de lignes : zéro ligne, l'offre s'ouvre elle-même. Tests vus rouges. |
| I4. Le texte tapé dans le bloc replié disparaissait de l'écran quand une fenêtre de l'offre se fermait | important | retenu | Le champ et la ligne des liens sont dans un seul morceau (`avant-lignes`). Prouvé par Playwright : texte tapé, objet changé dans sa fenêtre, le champ reste avec sa valeur. |
| I5. Pendant la seconde qui suit « Reprendre », la copie du téléphone avait une durée négative ; fermée à cet instant, la dictée était effacée à la réouverture | important | retenu | La pause soldée s'écrit avec l'heure (`pause` et `fin` ensemble). |
| M1. Arrêt pendant une pause : durée de la copie gonflée si une dernière tranche arrive | mineur | retenu | Même correction que I5 : `solderPause` écrit la copie au « Reprendre » comme à l'arrêt. |
| M2. `aria-live` basculé en même temps que le texte : l'alerte risquait de rester muette | mineur | retenu | Zone d'annonce à part (`role="status"`), qui ne reçoit que les alertes. Non vérifié avec un lecteur d'écran. |
| M3. Bouton Pause : `aria-pressed` et libellé qui change à la fois | mineur | retenu | `aria-pressed` retiré, le libellé dit l'état. |
| M4. 4 px entre le bouton et le lien de « Offre prête » | mineur | retenu | 8 px. |
| M5. Offre supprimée entre « prête » et la lecture de l'état : repli vers une page 404 | mineur | écarté | Comportement d'avant le chantier, cas à la seconde près ; à traiter avec la suppression des offres. |
| M6. Page de préparation déjà prête, offre propre : le bouton reste « Voir l'offre » | mineur | écarté | Voulu : cette page s'ouvre après coup (liste, courriel), l'offre est la bonne arrivée et le libellé dit vrai. |
| M7. Lignes ajoutées sans champ de quantité : le focus n'était rendu nulle part | mineur | retenu | Repli sur le bouton d'origine. |
| M8. La pièce d'une ligne est repliée sous « Plus de réglages » | à trancher | hors périmètre | Décision de Xavier (voir « Points à clarifier » du rapport). |
| M9. Bloc `{ }` nu dans `apercu-pdf.js` | mineur | retenu | Retiré. |
| M10. Commentaire faux sur « Modifier » dans `apercu_pdf.html.twig` | mineur | retenu | Corrigé. |
| M11. Échec de la préparation : l'écran ne disait pas où réessayer | mineur | retenu | « Fermez cette fenêtre : la dictée est dans « Offres », où vous pouvez la relancer. » |
| M12. `envoyee` compare à la seconde | négligeable | écarté | Une modification dans la seconde de l'envoi : sans effet réel. |
| M13. Les captures de `public/relecture-textes/ecrans/` montrent les anciens écrans | mineur | hors périmètre | Chantier de la relecture des textes : ses captures sont à refaire après ce chantier. |
| M14. `MediaRecorder` qui lève à la construction : contexte audio jamais fermé, écran bloqué | mineur | retenu | Tout se referme, le message propose l'import. Non rejoué (pas de navigateur qui lève). |

Signalé par le relecteur, sans changement de code : les bulles `a.attente` et `g.attente` du guide disent « en
quelques minutes » alors que l'anneau annonce « environ 10 s ». Ces textes ont été validés dans un autre chantier : à
trancher par Xavier.

Vérifié sans défaut par le relecteur : la portée de `/etat`, l'égalité des comptes (liste, bouton, relecture), les
appelants de ce qui a changé (`ETAPES`, la clé `dictee`, la classe `pleine`, `totauxEtARelire`), la fermeture du
contexte audio, les cas limites de la relecture, la barre de l'aperçu remplacée en morceau.

Compteur de délégation : relecteur sur Opus, hors compteur. Cinq trouvailles importantes sur cinq confirmées dans le
code.
