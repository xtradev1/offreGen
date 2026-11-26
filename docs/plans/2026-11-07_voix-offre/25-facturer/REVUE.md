# Revue du sous-chantier 25

Diff relu : `e711a6a..3cc8d01` (lots 1 à 7). Deux relecteurs indépendants (Opus), une lentille chacun, sans accès
à mes conclusions : correction (montants, numéros, états, JavaScript) et sécurité (isolation, CSRF, redirections,
injections). Chaque trouvaille a été vérifiée dans le code avant d'être traitée.

## Correction

| # | Trouvaille | Gravité | Suite |
|---|---|---|---|
| C1 | Un acompte refait (« Annuler et refaire ») qui attend en brouillon n'est pas déduit par la finale ; un acompte émis après la finale ne l'est jamais. Défaut d'avant le 25, rendu plus probable par E4 (acompte toujours possible). | à corriger | Corrigé : la finale ne s'émet pas tant qu'un acompte attend en brouillon ; un acompte ne s'émet plus après la finale émise (`emettreFacture`). Test `testUnAcompteEnBrouillonEtLaFinaleNeSeCroisentPas`. |
| C2 | Si le rendu du PDF ou la base lève une exception pendant « Créer la facture », le brouillon reste. | à corriger | Corrigé : création et émission dans une seule transaction, offre verrouillée ; refus ou panne, rien n'est écrit. |
| C3 | Double clic sur la page Facturer seule : deux acomptes émis. (Aussi relevé par la revue sécurité, S1.) | à corriger | Corrigé : jeton à usage unique dans la feuille, le second envoi retrouve la facture du premier ; bouton désactivé pendant l'envoi (`data-attente`). Test `testUnDoubleEnvoiNeCreeQuUneFacture` ; double clic vérifié au navigateur : 1 facture. |
| C4 | Montant en CHF sur une offre où un taux pèse presque rien : une ligne à 0.00, puis refus « ligne sans prix ». | mineur | Corrigé : une part nulle ne fait pas de ligne. Test `testUnePartNulleNeFaitPasDeLigne`. |
| C5 | Sans arrondi aux 5 ct, certains TTC ne s'atteignent pas : le plus proche peut dépasser d'un centime, et un montant égal au reste être refusé. | mineur | Corrigé pour le reste : on vise un ou deux centimes plus bas ; vérifié sur 44 369 montants, aucun ne reste au-dessus. Le reste du cas (le bouton annonce 1 ct de plus que le montant tapé, environ 7 % des montants au centime sans arrondi) est laissé : le bouton dit le vrai montant. |
| C6 | Acompte, « CHF » vide, puis « Facture finale » : l'aperçu de la finale ne revenait pas. | mineur | Corrigé (`app.js`) ; vérifié au navigateur. |
| C7 | L'échéance proposée ne suivait pas le genre choisi. | mineur | Corrigé : elle suit le genre tant que l'artisan ne l'a pas changée. Non observable sur la démo (même échéance pour les deux). |
| C8 | Entrée sur un bouton radio envoyait « Corriger des lignes » (premier bouton du formulaire). | mineur | Corrigé : Entrée recalcule partout ; vérifié au navigateur, rien de créé. |

## Sécurité

Contrôles sans trouvaille : CSRF (tout POST passe par `Garde::jetonValide`), isolation entre artisans sur chaque
chemin nouveau (dont `?ajoutee=`), redirection ouverte (`suite` n'accepte que `apercu`, `r.url` reste sur le site par
la CSP), XSS (aucun `|raw` ajouté, `textContent`, CSP sans script en ligne), SQL paramétré, aucune écriture en GET.

| # | Trouvaille | Gravité | Suite |
|---|---|---|---|
| S1 | Double facture d'acompte au double clic (= C3). | basse | Corrigé avec C3. |
| S2 | L'aperçu montrait un QR complet (IBAN, montant) avec une référence provisoire : un paiement fait sur une capture se serait rattaché à une autre facture. | basse | Corrigé : avant la création (aperçu, brouillon sans numéro), le cadre « Code QR à la création » sans code, référence « attribuée à la création » ; le PDF créé a le vrai code. |
| S3 | Une offre absurde (HT de l'ordre de 1e11 centimes) ou un paramètre envoyé en tableau donne une erreur 500 au lieu d'un message. | info | Laissé : seul l'artisan lui-même est touché, aucune donnée n'est écrite. |

## Preuves

```
$ phpunit --filter 'testUnDoubleEnvoiNeCreeQuUneFacture|testUnAcompteEnBrouillonEtLaFinaleNeSeCroisentPas|testUnePartNulleNeFaitPasDeLigne' (avant) : Failures: 3
$ phpunit : OK (472 tests, 2923 assertions) ; phpstan : [OK] No errors
$ php /tmp/c5.php : TTC essayés : 44369 ; au-dessus du voulu : 3243 ; encore au-dessus après la visée plus basse : 0
$ node lot8-revue.js (démo remise à zéro, PC)
  C8, Entrée sur le radio : url /offres/154/facturer · factures 0
  C6, finale après CHF vide : Créer la facture · CHF 3'106.80 · aperçu true
  C3, double clic : /factures/23/apercu · factures 1
  erreurs : aucune
```
