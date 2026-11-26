# Lot B : formule, mois gratuit, paiement. Découpage

Écrit le 2026-11-19, après les réponses de Xavier aux maquettes v4, v5 et v6 (`ETAT.md`). Ce fichier découpe le lot B en sous-lots ; chaque sous-lot aura son plan au moment de le coder. Le lot A (`PLAN-lot-A.md`) passe avant : le sous-lot B2 part de la démo.

## Ce qui est réglé

- Il n'y a pas d'essai à part : l'artisan prend une formule, son premier mois est gratuit, sans carte bancaire, sans engagement. Le mois commence au toucher sur « Commencer mon mois gratuit ».
- Au lancement, une seule formule : Essentiel, 1 compte, 50 offres par mois, CHF 44.90 par mois hors TVA (prix de l'étude du 17.11, à confirmer). Volume (2 comptes, 150 offres) et Équipe (3 comptes, 300 offres) ouvrent quand le besoin se fait sentir, vers 15 à 20 clients.
- À l'année : le prix s'affiche par mois. La première année offre 2 mois en plus du mois gratuit (CHF 449.00 pour 12 mois, soit CHF 37.42 par mois, 17 % de moins) ; ensuite 1 mois offert par an (CHF 493.90).
- Payer la première facture, c'est continuer. Sans paiement, le compte passe en lecture seule 30 jours, puis ses données sont supprimées ; rien n'est dû.
- La première facture part 7 jours avant la fin du mois gratuit. Rappels : 7 jours avant, 2 jours avant, 12 heures avant, tant que le paiement n'est pas arrivé. « J'ai payé » garde le compte ouvert 3 jours ouvrés, une fois par facture.
- Paiement : QR-facture au lancement, au mois comme à l'année. La carte (Stripe) s'intègre dès maintenant et reste éteinte jusqu'à une dizaine d'abonnés ; allumée, elle sert au paiement au mois.
- Deux entrées : le site (« Commencer mon mois gratuit », trois champs) et la démo lancée par un commercial. Le formulaire « Nouvel essai » est retiré.
- Au passage de la démo à la formule : tout est gardé, sauf l'offre d'exemple et son client ; les prix d'exemple jamais touchés restent, marqués « à vérifier ».
- Rappels sans forcer : `MAQUETTE-parcours-v4.html`, section « Rappeler sans forcer ».

Non décidé : une promo pour les premiers (H4). Rien n'est construit pour elle.

## Sous-lots

| Sous-lot | Objectif | Tâche Origin | Dépend de | Estimation de Claude |
|---|---|---|---|---|
| B1 | Formule sur le compte et quota par mois : 50 offres, remis à zéro à la date anniversaire ; la jauge « 12 offres ce mois sur 50 ». Sert aussi à l'espace artisan des membres de l'équipe (R4). | 461 | lot A1 | 6 h |
| B2 | De la démo à la formule : l'écran unique, le courriel réel et son lien d'accès, le nettoyage des exemples, les prix d'exemple « à vérifier », le ruban « Mois gratuit ». | 461 | lot A, B1, et la marque « exemple » du chantier des métiers | 6 h |
| B3 | Facture d'abonnement en QR-facture : page « Votre première facture » (au mois ou à l'année), émission 7 jours avant la fin, suivi dans `/admin` (« paiement reçu »), « J'ai payé », lecture seule, trois rappels par cron, renouvellement. | 462 | B2 | 14 h |
| B4 | Inscription par le site : `/commencer`, un mois gratuit par entreprise, frein contre les inscriptions en série, catalogue de départ du métier. | 465 | B1, B3 | 6 h |
| B5 | Carte par Stripe, livrée éteinte : page de paiement, écoute des événements, débit à la date anniversaire, interrupteur dans le `.env`. | 469 | B3 | 10 h |

Existant à reprendre : `src/Facture/QrFacture.php` (QR-facture et QR en SVG), `src/Compte/PlafondEssai.php` (plafond total, à côté duquel vient le quota par mois), `src/Compte/LiensAcces.php` (lien d'accès), `bin/` et `outils/deployer-test.sh` (tâches cron).

## Préalables, à fournir par Xavier

- Le créancier des QR-factures d'abonnement : raison sociale, adresse, IBAN.
- La TVA : assujetti ou non. Les prix sont affichés hors TVA ; la facture doit dire le taux et le numéro.
- Les conditions générales et la politique de confidentialité (tâche 463), avant le premier abonné payant : durée de conservation après un mois gratuit sans suite, résiliation, prix.
- Un compte Stripe, pour B5.
- Le site de production (tâche 459) : un abonné est un vrai client, ses données ne vont pas sur le site de test.

## Hors du lot B

- Les formules Volume et Équipe : plusieurs comptes sur un même catalogue, un sous-chantier à part.
- La promo des premiers (H4).
- Le contenu d'une offre ouvert 48 h par l'artisan pour le modérateur (R2).
- Le second facteur des comptes d'équipe : avant la production.

## Décisions fragiles

1. J'ai supposé que le quota repart à la date anniversaire du compte, pas le 1er du mois. Si Xavier veut le 1er, la jauge et la facture au prorata changent en B1 et B3.
2. J'ai supposé que « paiement reçu » se marque à la main dans `/admin` tant qu'il y a une dizaine d'abonnés. Si Xavier veut l'import du relevé bancaire dès le départ, B3 grossit d'environ 6 heures.
3. J'ai supposé qu'un compte en lecture seule garde l'accès à ses offres et à ses PDF, sans dictée, sans nouvelle offre, sans nouvelle facture. Si « lecture seule » doit aussi fermer les factures de l'artisan à ses propres clients, B3 est à revoir.

## Courriels de l'abonnement (textes relus le 2026-11-24, à coder avec B3)

Hypothèse de Xavier du 24.11, à confirmer avec le prestataire : paiement dès le passage à l'essai, remboursement complet pendant 30 jours. Réponses de la maquette v9 : le reçu de paiement est laissé au prestataire (D1) ; Dicta remercie pour la commande, jamais pour le paiement (V6, V9) ; les données d'un compte non payé sont gardées 6 mois, « voire 1 an » (V8, hypothèse retenue : 6 mois) ; le téléchargement des offres et des factures existe avant que le n° 8 parte (D2) ; avec l'essai payant, le courriel au commercial dit les 30 jours de remboursement et la date où sa commission est acquise (D3). Montants annoncés TVA comprise.

1. **Première commande** (une fois). Objet et titre : « Merci pour votre commande ». « Votre abonnement {formule} commence aujourd'hui. Vous pouvez dicter vos offres dès maintenant : » Bouton « Ouvrir Dicta ». Dessous : « Jusqu'au {date}, vous pouvez demander le remboursement complet, sans justification. La confirmation de paiement vous arrive de Payrexx, notre prestataire de paiement. »
2. **Lecture seule.** « Le paiement de votre abonnement, dû le {date}, n'est pas arrivé. Vous pouvez lire vos offres et vos factures. Vous ne pouvez plus en dicter de nouvelles. » Bouton « Payer mon abonnement ». Dessous : « Dès le paiement reçu, tout se rouvre. Vos données sont conservées 6 mois, jusqu'au {date}, puis supprimées. »
3. **Suppression annoncée** (7 jours avant). Titre « Vos données seront supprimées le {date} ». « Votre compte est en lecture seule depuis le {date}. Le {date}, nous supprimerons vos offres, vos factures, votre catalogue et vos clients. Téléchargez une copie de vos offres et de vos factures avant cette date : » Bouton « Télécharger mes offres et factures ». Dessous : « Pour garder votre compte, payez votre abonnement avant cette date : tout se rouvre. » Ne part pas tant que le téléchargement n'existe pas.
4. **Renouvellement annuel** (30 jours avant, à l'année seulement). Titre « Votre abonnement se renouvelle le {date avec l'année} ». « Merci de votre confiance. Votre abonnement annuel {formule} se renouvelle le {date avec l'année}, pour CHF {montant}, TVA comprise. » Bouton « Gérer mon abonnement ». Dessous : « Pour l'arrêter, faites-le avant le {date avec l'année} : rien ne sera débité. »
5. **Remboursement effectué.** Titre « Votre remboursement est en route ». « Nous vous remboursons CHF {montant}, le montant de votre premier mois. Selon votre banque, il arrive sous 5 à 10 jours ouvrés. » Bouton « Ouvrir Dicta ». Dessous : « Votre compte reste ouvert en lecture seule jusqu'au {date}. Ensuite, vos données seront supprimées. »

Au choix de Xavier (relecteur) : « Merci de votre confiance » (n° 4) jugé superflu, gardé parce que Xavier demande de remercier ; qui est « nous » ; le catalogue et les clients supprimés sans copie (n° 3).
