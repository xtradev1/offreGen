# Paiement de l'abonnement par Payrexx : état

Ouvert le 2026-11-23. Recherche et cadrage seulement : aucun code, aucun test, aucun déploiement.
Arrêté le 2026-11-24 à la demande de Xavier.

## Suivi Origin

- Projet 44 (offreGen). Conversation rattachée à la tâche 469 (paiement par carte et TWINT, prestataire à choisir).
- Tâche 568 : appeler le commercial de Payrexx, échéance 2026-11-24.
- Tâche 569 : rendez-vous avec une fiduciaire (Sàrl, TVA), échéance 2026-11-30. Sa description porte « intention du 2026-11-24 » au lieu du 23.
- Tâche 462 (abonnement par QR-facture) : sans objet si le choix de Payrexx est confirmé. Xavier la ferme lui-même.

## Décisions de Xavier (2026-11-23)

- Payrexx plutôt que Stripe, dès le lancement, sans QR-facture pour l'abonnement (« je pense partir avec eux », à confirmer après l'appel). Cela remplace deux points de `docs/plans/2026-11-19_admin-comptes/DECOUPAGE-lot-B.md` : la QR-facture au lancement (B3, 14 h) et la carte par Stripe livrée éteinte (B5, 10 h). Ni le découpage ni le roadmap ne sont modifiés.
- Abonnements gérés par Payrexx (voie A). Le prélèvement par jeton piloté par l'application (voie B) est écarté.
- Plus d'offre supplémentaire à l'unité : 50 offres par mois, au-delà la formule supérieure.
- Prix : CHF 45 ou 44.90 hors TVA, sans importance pour lui.
- XtraDev est une raison individuelle non assujettie à la TVA (moins de CHF 100 000 de chiffre d'affaires).
- Objectif : CHF 2 000 à 3 000 par mois fin 2027, soit 50 à 100 abonnés.
- Sàrl : motif de protection et de structure propre, sans lien avec la TVA. Xavier consulte une fiduciaire qu'il connaît.

## Constats (lus à travers le moteur de recherche, grilles officielles non ouvertes)

- Payrexx : la formule Free n'a pas d'API. Standard CHF 19 par mois, réservée pour 1, 2 ou 3 ans, carte 1,65 % + 0.18, TWINT 1,25 % + 0.18. Premium CHF 49, carte 1,35 % + 0.18. Essai de 30 jours avec toutes les fonctions.
- Stripe : carte suisse 2,9 % + 0.30, TWINT 1,9 % + 0.30, Billing environ 0,7 % en plus. TWINT récurrent depuis le 2026-05-27.
- Sur CHF 45 sans TVA : Payrexx Standard 0.92 par carte et 0.74 par TWINT ; Stripe 1.92 et 1.47. Payrexx coûte moins dès 22 abonnés. À 100 abonnés : CHF 102.25 contre 169.50 par mois.
- API : en-tête `X-API-KEY`, instance en paramètre, version 1.16, paquet `payrexx/payrexx` (PHP 8.0 au minimum, PHP 8.5 non vérifié). Abonnement par `subscriptionState=1` et intervalle `P1M`. Webhook signé par `X-Webhook-Signature` (HMAC SHA-256 en hexadécimal du corps brut), renvoyé jusqu'à 10 fois, réponse attendue en 20 secondes.
- Relances : statut « overdue », une tentative par nuit pendant 7 jours, puis « failed » définitif. Courriel « Failed Charge » au client. Espace client par `AuthToken` (changer de moyen de paiement, résilier). Changement de montant par l'API au prochain intervalle.
- Litiges : CHF 35 prélevés à l'ouverture et rendus si le marchand gagne, montant retiré, versements suspendus, preuves déposées une seule fois, décision de la banque en 60 à 75 jours.
- Versements : hebdomadaires par défaut, CHF 0.30 par versement, délai de 4 à 8 jours.
- Mode test : un interrupteur « Live / Test » sur le prestataire du compte. Test et production en parallèle demandent probablement deux instances.
- Changement de forme juridique : annonce écrite sous 3 jours, nouvelle vérification, compte bancaire au nom de la société. La reprise des abonnements actifs n'est pas documentée.
- TVA : la libération prend fin à la clôture de l'exercice du dépassement. Décompte annuel possible sur demande depuis 2025.

## Précautions de conception proposées par Claude

1. L'application décide de l'accès, Payrexx encaisse : date de fin de période payée sur le compte, délai de grâce de 30 jours.
2. Chaque abonnement enregistre l'instance Payrexx dont il dépend, pour que deux comptes coexistent pendant un passage en Sàrl.
3. Clause de cession du contrat à une société reprenant Dicta, dans les conditions générales (tâche 463).
4. Taux de TVA de l'abonnement réglable, à 0 aujourd'hui, et clause « prix hors TVA ».

## Non vérifié

- Webhook au passage en « overdue », échec d'un prélèvement TWINT, TWINT par jeton.
- Reçu ou facture envoyé au client, alerte de carte bientôt expirée, mois offert, remise durable.
- Délai de vérification du compte, TVA sur les frais de Payrexx, taux de la dette fiscale nette pour un logiciel.
- Canton de Xavier (Jura supposé), chiffre d'affaires des mandats XtraDev.

## Reste à faire

- Appel à Payrexx avec `QUESTIONS-payrexx.md` (30 questions), puis rendez-vous avec `QUESTIONS-fiduciaire.md` (21 questions). Noter les réponses dans ces fichiers.
- Reporter le choix du prestataire dans le roadmap et dans le découpage du lot B, une fois confirmé.
- Essai en mode test, 2 à 3 h : un abonnement payé par carte et par TWINT de test, chaque webhook imprimé.
- Développement de la voie A : 10 à 14 h, estimation de Claude sans compte de test.

## Erreur de la session

Jusqu'au dernier échange, Claude a lu la copie SynologyDrive du dépôt, périmée selon la mémoire du projet. Le cadrage à 49 CHF et l'hypothèse de 3 % de frais qu'il a critiqués étaient dépassés : le lot B portait déjà CHF 44.90 et 3,6 % + 0.30.
