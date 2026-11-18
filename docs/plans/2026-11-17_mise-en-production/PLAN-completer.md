# Plan : compléter l'application sans attendre la marque

Demande de Xavier du 2026-11-18 : « l'envoi de courriels et les jetons de mot de passe et d'accès (A3) ; le droit administrateur ; la purge écrite et désactivée (Q1) ; les alertes vers ton adresse (Q3) ; ensuite l'avertissement des postes coupés (A13) et l'enregistrement à l'abri (sous-chantier 27) ».

Les écrans P1, P2, P4 et A2 ont été refusés pour leur aspect, avec un parcours accepté. Les pages de ce plan reprennent donc les composants actuels de `public/css/app.css` : elles seront rhabillées avec la marque, sans changer de parcours.

## Lots

| Lot | Contenu | Test qui échoue avant |
|---|---|---|
| 1. Messagerie | `Messagerie` passe dans `src/Systeme/`. Envoi réel par `symfony/mailer` 7.4 (`MESSAGERIE=smtp`, `MAILER_DSN`, `COURRIELS_EXPEDITEUR`), fichier `.eml` sinon. `COURRIELS_REDIRIGER_VERS` : tout part vers une seule adresse, destinataire d'origine dans l'objet (site de test, `docs/SECURITE.md` ligne 13). Aucun courriel vers un compte de démonstration. | redirection, démonstration, choix du transport |
| 2. Droit administrateur | Colonne `artisan.administrateur`. Droit de route `Route::ADMIN` : 404 pour tout autre compte et sans session (A1). `bin/administrateur <courriel> oui|non`. | route admin : 404 sans session, 404 pour un artisan, 200 pour l'administrateur |
| 3. Jetons | Table `jeton_acces` (empreinte SHA-256 seulement, motif, échéance, usage). Oubli : 1 heure, 3 demandes par heure et par compte, même page que le compte existe ou non, courriel envoyé après la réponse. Accès (A3 = A) : 7 jours, `bin/creer-compte --acces`. Un lien sert une fois ; l'utiliser annule les autres liens du compte. Nouveau mot de passe : deux champs masqués et l'œil qui les montre (P3 = B), 10 caractères au moins. Rubrique « Mot de passe » des réglages (P4) : ancien et nouveau ; changer le mot de passe déconnecte les autres appareils (`artisan.version_session`, M6). | jeton expiré, réutilisé, inconnu ; même réponse pour un compte inconnu ; plafond de demandes ; autres sessions fermées |
| 4. Purge | `PURGE_AUDIO=1` l'active ; absent ou autre valeur : `bin/purger-audio` ne supprime rien et le dit. Règle Q1 = A : audio effacé 30 jours après l'offre, texte gardé. La ligne cron est posée sur le test, purge éteinte. | purge éteinte : aucun fichier supprimé |
| 5. Alertes | Les erreurs du journal (niveau `error` et plus) partent par courriel à `ALERTES_A`. Au plus un courriel par heure pour la même erreur, 30 par jour en tout. Sans `ALERTES_A`, rien ne part. | même erreur deux fois : un seul courriel ; plafond du jour |
| 6. Postes coupés (A13) | Déjà codé et déployé le 2026-11-18, C1 validé : preuve des tests existants seulement. | (existants) |
| 7. Enregistrement à l'abri (27) | Spec, puis code : chaque tranche dans le stockage du navigateur pendant l'enregistrement, suppression quand l'offre est prête, reprise à la réouverture dans une fenêtre qui monte (R1 = B, texte plus clair). | à écrire avec la spec |

## Hors de ce plan, noté pour la suite

- Coût du jour anormal (Q3) : il faut le journal des appels IA (sous-chantier 26). Sans lui, aucune alerte de coût.
- Site arrêté : seul un contrôle extérieur peut le voir. À prévoir avec le workflow de production (étape 6) : un appel planifié à `/sante`.
- Un plafond de demandes par adresse IP pour « mot de passe oublié » : le plafond par compte borne déjà le nombre de courriels reçus par une même personne.
