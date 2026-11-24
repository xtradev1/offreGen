# SPEC : prospects, le catalogue des commerciaux

Version 1 du 2026-11-24. Les phrases entre guillemets sont de Xavier, dites le 2026-11-24. Les lectures et les recommandations de Claude sont marquées comme telles.

## Problème

- Un artisan n'existe dans l'application qu'à partir de sa démo (`artisan.demo`, `cree_par`, `apporte_par`). Aucune table ni aucune route ne porte un prospect : constaté le 2026-11-24 par `grep -rli prospect src migrations config`, qui ne trouve que le courriel tapé par le visiteur d'une démo.
- L'artisan rencontré sans démo vit hors de l'application. Deux commerciaux peuvent démarcher le même artisan sans le savoir.
- « Mon suivi » liste les démos en cours et les demandes d'appel. Il ne porte ni rendez-vous ni prochain pas.
- Demande d'origine : la responsable commerciale veut « noter les prospects », « qui est-ce qu'on va voir quand, qui s'en occupe ».

## Utilisateurs

| Rôle | Ce qu'il fait | Ce qu'il voit |
|---|---|---|
| Commercial | saisit un prospect, le prend, note, fixe le prochain pas, lâche, crée la démo | ses fiches en entier ; les fiches libres en entier ; des fiches des collègues : selon P2 de la maquette |
| Modérateur | lit, saisit, prend comme un commercial | toutes les fiches, notes comprises (« Dans un premier temps, je dirais oui. Après, on peut limiter ce rôle. ») |
| Administrateur | comme le modérateur, et fusionne deux fiches | tout |

Le rôle de la responsable commerciale dans l'application reste à nommer (administrateur ou modérateur).

## Périmètre

1. Une rubrique « Prospects » dans `/admin`, pour les trois rôles.
2. Une fiche par entreprise : nom, localité (numéro postal), métier, téléphone, courriel, personne de contact, origine de la rencontre, numéro IDE facultatif tapé à la main.
3. Un état saisi parmi cinq : à contacter, contacté, rendez-vous, plus tard, pas intéressé.
4. Un état calculé, jamais saisi, quand un compte est lié : démo en cours, mois gratuit, client.
5. Un prochain pas par fiche (texte court et date), avec « C'est fait ».
6. Des notes datées, en ajout seulement.
7. Trois listes : « Les miens » (pas dus en tête), « Libres », « Tous ».
8. « Je prends » sur une fiche libre, « Je lâche ce prospect » sur une fiche à soi. Personne ne prend ni n'attribue pour un autre (« Le commercial prend lui-même un prospect »).
9. Une fiche laissée sans suite redevient libre (règle exacte : P3 de la maquette). La fiche prévient deux jours avant.
10. Chercher avant de créer : la recherche couvre les prospects et les comptes (démos, essais, clients).
11. Avertissement à l'enregistrement sur un téléphone, un courriel, ou un nom et un numéro postal déjà connus ; refus sur un numéro IDE déjà connu, avec ouverture de la fiche existante.
12. « Créer une démo » depuis la fiche, champs préremplis, compte lié à la fiche. Une démo créée sans fiche crée la sienne.
13. Fusion de deux fiches par l'administrateur, notée au journal de l'administration.

## Hors périmètre

- Attribution d'un prospect par un tiers, objectifs, classement des commerciaux : contraire à la section 3 des règles de commission (indépendance) et à la demande de Xavier (« c'est pas qu'il y ait des ordres qui soient donnés »).
- Tout droit sur un prospect né de la liste. La liste informe (« c'est juste pour dire qu'il y a déjà quelqu'un ») ; la commission reste réglée par C1 (code tapé à l'inscription).
- Recherche dans le registre du commerce ou le registre IDE : version 2, quand l'accès sera confirmé (API de Zefix non confirmée sur un site officiel, registre IDE annoncé comme remplacé par le BER, date non trouvée).
- Import en masse d'un fichier d'entreprises.
- Envoi de courriels ou de messages aux prospects depuis la fiche.
- Agenda, synchronisation de calendrier, rappels par courriel au commercial.
- Tableur provisoire avant la production (« On peut faire rien pour l'instant »).

## Critères de succès

- Un commercial saisit un artisan rencontré en moins de 30 secondes au téléphone, avec deux champs obligatoires.
- La saisie d'un artisan déjà présent (prospect ou compte) affiche la fiche existante avant la création.
- Un commercial voit, pour tout prospect, s'il est libre ou suivi, et par qui selon P2.
- Une fiche sans suite redevient libre à l'échéance de P3, sans intervention.
- La démo créée depuis une fiche porte le lien vers elle, et l'état de la fiche suit le compte.
- Un commercial ne lit par aucune route le téléphone ni les notes d'une fiche suivie par un collègue (si P2 = A ou C), prouvé par un test par route.

## Contraintes

- `CODING_RULES.md`, `docs/SECURITE.md`, `docs/DEPLOIEMENT.md`.
- Réutiliser : `PorteeEquipe` et `DepotEquipe` pour la portée par rôle, `JournalAdmin` pour le journal, `DemandesAppel::telephone` pour le numéro, les composants de `admin.css` et `suivi.css` (liste, carte « À rappeler », puces, tableau).
- Cibles tactiles de 48 px, barre d'action de 64 px, boutons anthracite (décisions du 19.11).
- Surfaces sensibles touchées : droits par route, filtre par rôle. La revue lourde s'applique en phase 7.
- Le site de test ne reçoit que des données inventées : la rubrique s'y recette avec des artisans inventés.

## Risques

- **Production absente.** Les vrais prospects ne peuvent être saisis nulle part tant que le site de production n'existe pas (à créer par Xavier). Livrer sur le test ne sert que la recette.
- **Données personnelles.** Notes sur des raisons individuelles et des personnes de contact : mention dans la politique de confidentialité, durée de garde, à traiter avec le lot juridique (Origin 463). Proposition de Claude : effacement d'une fiche « pas intéressé » 24 mois après la dernière action.
- **Indépendance des commerciaux.** Le modérateur et l'administrateur lisent tout. Lire reste compatible avec la section 3 des règles ; toute fonction d'attribution la contredirait. Question 4 du juriste.
- **Fuite entre commerciaux.** Si P2 = A ou C, le cloisonnement doit tenir sur chaque route, recherche comprise (la recherche ne doit rien rendre des champs masqués).
- **Libération contournable.** Avec P3 = B, un pas daté tous les 30 jours garde un prospect indéfiniment. Accepté tant que la liste ne donne aucun droit.
- **Notes après changement de main.** P6 : les notes d'un commercial indépendant passent au suivant, ou non.
- **Doublons résiduels.** Sans registre, deux orthographes d'une même entreprise sans téléphone commun passent. La fusion sert à cela.
- **Priorité.** Aucun client payant, mise en production en cours, plusieurs recettes en attente : ce chantier s'ajoute à la file.
