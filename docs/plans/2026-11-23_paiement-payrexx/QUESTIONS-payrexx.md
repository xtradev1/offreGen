# Appel au commercial de Payrexx : questions

Préparé le 23.11.2026 (tâche Origin 568). Les réponses se notent sous chaque question.

## Me présenter en 30 secondes

- XtraDev, raison individuelle, non assujettie à la TVA.
- Dicta : application web par abonnement pour les artisans, qui transforme une dictée en offre.
- Prix : CHF 44.90 par mois, ou à l'année (CHF 449 la première année, CHF 493.90 ensuite). Premier mois gratuit, sans moyen de paiement.
- Objectif : 50 à 100 abonnés fin 2027.
- Besoin : des abonnements gérés par Payrexx, payés par carte et par TWINT, branchés par l'API en PHP. Mon application écoute les webhooks et ouvre ou ferme l'accès.

## Les quatre questions qui décident

1. Je démarre en raison individuelle et je créerai peut-être une Sàrl. Le compte et les abonnements actifs sont-ils repris par la société, ou faut-il ouvrir un nouveau compte et faire revalider chaque abonné ?
   - Réponse :
2. La formule Standard existe-t-elle au mois, ou seulement avec un engagement d'un an ?
   - Réponse :
3. Mon site de test et mon site de production peuvent-ils tourner en même temps ? Faut-il une seconde instance, et à quel prix ?
   - Réponse :
4. TWINT fonctionne-t-il pour un abonnement mensuel reconduit automatiquement ? Le client doit-il valider chaque prélèvement dans son application ?
   - Réponse :

## Formule et contrat

5. L'API est-elle bien absente de la formule Free ? Standard suffit-il pour créer des abonnements par l'API ?
   - Réponse :
6. Quel préavis pour résilier ? Que deviennent les abonnements de mes clients si je résilie ?
   - Réponse :
7. À partir de quel volume les taux se négocient-ils ?
   - Réponse :

## Compte et vérification

8. Combien de temps dure la vérification du compte ? Un logiciel par abonnement est-il classé à risque ?
   - Réponse :
9. TWINT est-il disponible en mode test, y compris pour un abonnement ?
   - Réponse :

## Abonnements gérés par Payrexx

10. Un abonnement à l'année est-il possible ? Puis-je changer son montant au renouvellement (CHF 449, puis CHF 493.90) ?
    - Réponse :
11. Un changement de montant par l'API prend-il effet tout de suite ou au prochain intervalle ?
    - Réponse :
12. Mon serveur reçoit-il un webhook au premier échec de prélèvement (« overdue »), puis à l'échec définitif (« failed ») ?
    - Réponse :
13. Quels courriels partent à mon client ? En français, à mes couleurs ? Puis-je les désactiver et envoyer les miens ?
    - Réponse :
14. Mon client reçoit-il un reçu ou une facture à chaque prélèvement ?
    - Réponse :
15. Comment offrir un mois à un abonné (parrainage) : pause, report de la date, montant à zéro ?
    - Réponse :
16. Comment appliquer une remise durable de 50 % à un abonné ?
    - Réponse :
17. L'espace où mon client change sa carte et résilie est-il en français et à mes couleurs ?
    - Réponse :
18. Un client peut-il payer son abonnement à l'année par QR-facture à travers Payrexx ?
    - Réponse :
19. Votre documentation demande de vous contacter quand on gère les abonnements depuis sa propre application. Qu'attendez-vous de moi ?
    - Réponse :

## Litiges et remboursements

20. Les CHF 35 sont-ils dus à l'ouverture de chaque litige ? Sont-ils rendus si je gagne ?
    - Réponse :
21. Pendant un litige, tous mes versements sont-ils suspendus, ou seulement le montant contesté ? Combien de temps ?
    - Réponse :
22. Quel est le délai réel pour déposer mes preuves ?
    - Réponse :
23. Les litiges TWINT suivent-ils les mêmes règles et les mêmes frais ?
    - Réponse :
24. Quand je rembourse un client, vos frais de transaction me sont-ils rendus ?
    - Réponse :
25. Combien de litiges voyez-vous chez vos clients qui vendent un logiciel par abonnement ?
    - Réponse :

## Versements et comptabilité

26. Quel délai et quel rythme de versement pour un nouveau compte ? Une réserve est-elle retenue ?
    - Réponse :
27. Vos frais sont-ils soumis à la TVA ? Quel relevé fournissez-vous pour ma comptabilité ?
    - Réponse :
28. Quel libellé apparaît sur le relevé bancaire de mon client ? Puis-je le choisir ?
    - Réponse :

## Fiabilité et support

29. Les retards de versement de juillet 2026 et la panne partielle de l'API du 2 juin 2026 : quelle cause, quels correctifs ?
    - Réponse :
30. Quel engagement de disponibilité ? Quel support en français, par téléphone ?
    - Réponse :

## Après l'appel : ce que les réponses décident

- Question 1, « repris par la société » : je démarre en raison individuelle sans risque.
- Question 1, « nouveau compte » : soit la Sàrl est créée avant le premier abonné payant, soit chaque abonné revalide son paiement une fois, pendant un délai de grâce de 30 jours géré par l'application.
- Question 4, « validation à chaque prélèvement » : TWINT sert au paiement à l'année, la carte au paiement au mois.
- Question 2, « engagement d'un an » : CHF 228 engagés, accepté.
- Questions 15 et 16 sans solution : l'application gère elle-même le mois offert et la remise, en changeant le montant par l'API.
