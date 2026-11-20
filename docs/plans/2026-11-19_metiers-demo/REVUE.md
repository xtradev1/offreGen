# Revue : métiers de la démo et de l'essai

Phase 7, 2026-11-20. Deux relecteurs indépendants (Opus), en lecture seule, une lentille chacun : les données et le passage de la démo à l'essai (commit 555dea4) ; l'écran « Vos prix » (commits 727f0dd à 5d62ba8). Chaque trouvaille reprise ci-dessous a été relue dans le code par la session principale avant d'être corrigée. Le détail des relecteurs (scénario, ligne) n'est pas versionné.

## Données et passage à l'essai

| # | Trouvaille | Gravité | Vérifiée dans le code | Suite |
|---|---|---|---|---|
| D1 | Le passage laisse au compte l'adresse inventée de la démo, son mot de passe partagé et ses sessions ouvertes ; `--acces` écrit à l'adresse inventée | bloquant si la démo a servi à plusieurs | oui (`MotDePasseControleur` : « un compte de démonstration est partagé ») | corrigé : `passerEnEssai(démo, adresse de l'artisan, offres)`, mot de passe neuf, numéro de session avancé ; trois tests |
| D2 | Rattacher une ligne à un produit d'exemple lui ajoute un synonyme par `modifier()`, qui effaçait la marque : le prix inventé restait dans le catalogue de l'essai | à corriger | oui (`LigneCatalogueControleur::rattacher`) | corrigé : la marque tombe seulement quand le prix change ; un test |
| D3 | Une démo posée avant la migration n'a aucune marque : le passage réussissait sans rien nettoyer | à corriger | oui | corrigé : refus si le compte n'a pas de métier, avec la marche à suivre ; un test |
| D4 | Une offre d'exemple corrigée, envoyée ou facturée est effacée, factures émises comprises | décision de Xavier | oui | inchangé : la spec demande de retirer les deux offres d'exemple ; question posée à Xavier |
| D5 | Le contrôle « compte de démo » était lu hors transaction | mineur | oui | corrigé : `sortirDeDemo` n'écrit que si `demo = 1`, sinon tout est annulé |
| D6 | `sortirDeDemo` écrase `offres_max` sans regarder les offres offertes par l'équipe | mineur | non relu | laissé : une démo n'a pas de plafond total ; à revoir avec le chantier des comptes |
| D7 | Le texte du métier acceptait sauts de ligne, séquences d'échappement et octets illisibles | mineur | oui | corrigé : refusés ; deux cas de test |
| D8 | `--offres` sans borne haute | mineur | oui | corrigé dans la commande (six chiffres au plus) ; la méthode garde son contrôle « au moins 1 » |
| D9 | Le test des factures n'utilise que des factures annulées ou en brouillon | mineur | oui | laissé, lié à D4 |

Les visites dictées par d'autres visiteurs d'une même démo restent dans l'essai : aucun code ne peut les distinguer. La commande l'écrit dans son en-tête et affiche le nombre de visites gardées.

Sans défaut trouvé par le relecteur : isolation entre comptes, transaction unique et ordre des clés étrangères, numérotation des offres, migration additive.

## Écran « Vos prix »

| # | Trouvaille | Gravité | Vérifiée dans le code | Suite |
|---|---|---|---|---|
| V1 | Le script rechargeait la page à chaque retour de l'historique, sur « Mes offres » et le catalogue, où `app.js` ferme ses fenêtres par l'historique : fiche du catalogue, feuille de dictée, enregistrement en cours | bloquant | oui (`app.js`, `calques`) | corrigé : rechargement seulement après une arrivée sans navigation |
| V2 | Sur le dernier produit, le message « Annuler » se perdait (consommé par la requête du script, absent du bilan rechargé) | à corriger | oui | corrigé : la dernière carte part par l'envoi ordinaire (`data-dernier`) ; un test |
| V3 | L'arrivée sans navigation remplace le bas de l'écran : sur PC, « Dicter une offre » et la touche D perdaient leurs écouteurs | à corriger | oui | corrigé : arrivée sans navigation sur écran tactile seulement |
| V4 | Double envoi : « Passer » deux fois faussait le compte ; une erreur du script après la réponse renvoyait le formulaire une seconde fois | à corriger | oui | corrigé : « Passer » ne compte qu'une fois (un test) ; plus aucun renvoi après une réponse du serveur |
| V5 | Sans `submitter` (Safari avant 15.4), chaque bouton partait comme « Suivant » | à corriger | oui | corrigé : le bouton touché est retenu |
| V6 | Rien n'annonce le produit suivant au lecteur d'écran ; le message disparaît même avec le curseur | à corriger | oui | corrigé en partie : nom du produit annoncé (`aria-live`), message gardé s'il a le curseur ; l'ordre de tabulation (« Suivant » d'abord) est voulu |
| V7 | Rien ne montre qu'un envoi est en cours | à corriger | oui | corrigé : carte atténuée pendant l'envoi |
| V8 | « Annuler » remettait le prix à 0 même si l'artisan l'avait changé entre-temps | mineur | oui | corrigé ; un test |
| V9 | Le glisser pouvait faire « Passer » pendant un défilement vertical | mineur | oui | corrigé : geste jugé à la fin, horizontal ou rien |
| V10 | « Suivant » et « Passer » à 56 px | mineur | oui | corrigé : 64 px |
| V11 | Bandeau, écran et catalogue ne comptent pas les mêmes produits sans prix | mineur | oui | voulu : décision du 2026-11-19 dans `ETAT.md` |
| V12 | Après une erreur de saisie, « Reprendre » disparaît | mineur | oui | voulu : maquette validée (le message prend la place de la suggestion) |
| V13 | Pas de garde contre un double toucher à l'ouverture | mineur | oui | corrigé |

Sans défaut trouvé par le relecteur : contrôle CSRF de chaque envoi, portée de chaque requête, session côté serveur, échappement par Twig, redirections bâties sur des chemins fixes.

## Ce que la revue ne couvre pas

- Le script n'a aucun test automatique : V1 à V5 passaient PHPUnit au vert. Ses preuves sont le parcours joué sur le site de test et l'essai de Xavier sur iPhone.
- Les quatre tests ajoutés pour V2, V4 et V8 ont été écrits après la correction du contrôleur : ils n'ont pas été vus rouges. Ceux de D1, D2, D3 et D7 l'ont été (10 erreurs, 2 échecs).
- Non vérifié par les relecteurs : le comportement dans un navigateur, le mode SQL du serveur de test, les contrastes en thème sombre.
