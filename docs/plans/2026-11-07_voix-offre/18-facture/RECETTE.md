# Recette du sous-chantier 18 : la facture

Tout tourne sur ton PC (`10-ia-locale/RECETTE.md`, partie « Lancer »). Remets d'abord la démo à zéro dans Ubuntu :

```
cd ~/offreGen && php bin/reinitialiser-demo demo-peintre@exemple.ch peintre
```

Ouvre http://localhost:8000, connecte-toi avec `demo-peintre@exemple.ch` (mot de passe : `cat ~/.offregen-demo-compte`).
Le téléphone ne voit pas `localhost` : fais la recette sur le PC, fenêtre étroite si tu veux voir l'écran du téléphone.

| # | Geste | Attendu |
|---|---|---|
| 1 | « Réglages », « Mon entreprise » : tape l'IBAN `CH93 0076 2011 6238 5295 7` (celui des exemples de SIX), puis touche un autre champ | « Enregistré » ; l'IBAN reste groupé par quatre |
| 2 | « Offres », « Rafraîchissement du séjour », aperçu, « Facturer » | deux choix : « Acompte 1 sur 3 (30 %) », CHF 862.20 hors TVA, et « Facture finale » ; numéro proposé 2026-001, échéance à 30 jours ; le client de l'offre |
| 3 | Garde l'acompte, change le numéro en 2026-050, « Créer la facture » | « Facture 2026-050 créée », brouillon, total TTC CHF 932.05 |
| 4 | « Voir la facture » | le document, puis la section de paiement au bas ; « Payable jusqu'au … avec la section de paiement ci-dessous » |
| 5 | « Émettre et PDF », puis confirme | « Facture 2026-050 émise » ; la facture ne se modifie plus ; « PDF » télécharge une page, section au bas, « À détacher avant le versement » au-dessus du trait |
| 6 | Scanne le code du PDF avec ton application bancaire, **sans payer** | elle lit : Peinture Morel Sàrl, l'IBAN, CHF 932.05, une référence qui commence par RF, « Facture n° 2026-050 » |
| 7 | Reviens à l'offre, « Facturer » | « Acompte 2 sur 3 » proposé, numéro 2026-051 ; « Déjà facturé : Facture 2026-050, acompte 1 » |
| 8 | Choisis « Facture finale », « Créer la facture » | toutes les lignes de l'offre ; « Acomptes déjà facturés − 932.05 » ; « À payer CHF 2'174.75 » |
| 9 | Change une quantité de la facture finale | le total et « À payer » suivent |
| 10 | « Voir la facture », « Émettre et PDF » | le PDF a deux pages : les lignes, puis la section de paiement seule sur la page 2 ; la condition dit « de la page suivante » ; le montant du code est celui « À payer » |
| 11 | Sur la facture émise : « Annuler la facture », puis « Annuler et refaire » | 2026-051 annulée ; nouveau brouillon 2026-052, à corriger puis émettre |
| 12 | Onglet « Factures », cherche « 052 » | 3 factures ; 2026-051 barrée ; la recherche garde la seule 2026-052 |
| 13 | L'offre : en bas, les liens « Facture 2026-050 », « 2026-051 », « 2026-052 » | chacun ouvre sa facture |
| 14 | « Réglages » : efface l'IBAN, puis ouvre l'aperçu du brouillon 2026-052 | « Sans IBAN, la facture n'a pas de section de paiement : compléter dans les réglages », avec le lien |

Dis-moi ce qui gêne : un mot qu'un artisan ne comprendrait pas, un geste de trop, un montant que tu calculerais autrement.

## Limites connues

- Pas d'envoi par courriel (sous-chantier 19, « pdf pour l'instant ») ; pas de suivi des paiements reçus : une facture reste « Émise, payable jusqu'au … ».
- L'adresse de la démo, « Rue de la Gare 4, 2800 Delémont », n'existe pas au registre. OpenCV lit le code ; aucune banque ne l'a encore lu, c'est l'étape 6.
- Section de paiement en Helvetica, police permise par la norme : un nom avec un caractère hors de l'alphabet d'Europe de l'Ouest s'y affiche mal ; le code reste juste.
- Une facture émise est gardée telle quelle en PDF : changer le logo ou l'adresse ensuite ne la change pas, c'est voulu.
- Le numéro choisi à la main (étape 3) devient la base des suivants : 2026-050 propose ensuite 2026-051.
