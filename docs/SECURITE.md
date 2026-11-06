# Socle de sécurité

Règles opposables dès le premier jour. Une règle sans objet s'écrit « sans objet » avec sa raison.
Toute entorse se justifie dans les décisions du `PROJECT_ROADMAP.md`. État à tenir à jour : `[ ]` à
faire, `[x]` fait et prouvé par une commande ou une capture citée sur la ligne.

## 1. Exposition
- [ ] La racine web pointe sur `public/`. Code, configuration, dépendances, journaux, sauvegardes et fichiers déposés vivent hors de cette racine.
- [ ] Tout est refusé par défaut. Seul le contrôleur frontal s'exécute. Fichiers cachés et listings refusés.
- [ ] HTTPS forcé, HSTS en production, en-têtes de sécurité et CSP fermée.
- [ ] `.htaccess` et configuration serveur versionnés, jamais modifiés à la main sur le serveur.
- [ ] Erreurs détaillées et mode debug coupés hors du poste de développement.
- [ ] Un environnement de test est protégé dès sa création : authentification en amont, indexation bloquée, courriels bloqués ou redirigés.

## 2. Moindre privilège
- [ ] Base de données : un utilisateur par environnement. L'application n'a que les droits lecture et écriture. Les droits de structure sont réservés à un second utilisateur, utilisé uniquement pendant une migration.
- [ ] Le mot de passe de ce second utilisateur reste dans les secrets du déploiement, jamais sur le serveur.
- [ ] Un compte SSH par environnement, par clé `ed25519`, limité au dossier de cet environnement.
- [ ] Double authentification sur GitHub et sur le compte de l'hébergeur.
- [ ] Rôles applicatifs : refus par défaut, droit vérifié côté serveur à chaque requête.
- [ ] Claude suit la règle « Production » du projet (mémoire `feedback`).

## 3. Permissions du dépôt et de la CI
Sur un dépôt privé, GitHub Free n'offre ni branche protégée ni secret par environnement : il faut au moins Pro. Une exception s'écrit dans les décisions du roadmap.
- [ ] GitHub Free, sans branche protégée (décision de Xavier du 2026-11-06, entorse au roadmap) : la mise en production refuse tout commit sans `controles-test-verts`, et Claude ne pousse plus directement sur `main` après le premier commit.
- [ ] Chaque workflow déclare `permissions:` au minimum (par défaut `contents: read`).
- [ ] Actions tierces épinglées par SHA complet et vérifiées sur leur dépôt d'origine.
- [ ] Sans secret par environnement : seul le job de mise en production cite les secrets de production. Il reçoit une version construite par un job sans secret, n'installe aucune dépendance et n'exécute que le script de déploiement.
- [ ] La clé SSH de production est limitée par une commande forcée au seul script de déploiement (à prouver sur Infomaniak).
- [ ] Aucun secret dans le code, l'historique, une URL ou les journaux. `.env` et `*.local.*` ignorés par git.

## 4. Dépendances
- [ ] Fichier de verrouillage versionné. La CI et le déploiement installent strictement ce fichier (`composer install`, `npm ci`).
- [ ] Scripts d'installation et plugins bloqués, sauf liste blanche écrite (`allow-plugins`, `ignore-scripts`).
- [ ] Audit des dépendances en CI (`composer audit`, `npm audit`) : une faille haute ou critique bloque.
- [ ] Le même audit tourne chaque jour par une tâche planifiée, hors de toute PR, et alerte Xavier : un avis publié pendant une période sans activité sur le dépôt est vu le jour même.
- [ ] Chaque dépendance ajoutée se justifie en une ligne. Une mise à jour passe par une PR.

## 5. Données multi-clients
Sans objet seulement pour une application à client unique, avec la raison écrite ici.
- [ ] Chaque ligne métier porte l'identifiant du client. Le filtre s'applique dans une couche unique, et un accès sans client refuse.
- [ ] Un test automatique prouve, route par route, que le client A n'atteint aucune donnée du client B, y compris les fichiers et les exports.
- [ ] Journal des connexions et journal des modifications, séparés et exportables.
- [ ] Données sensibles chiffrées au repos quand elles le justifient. Clés hors de la base.
- [ ] Sauvegardes chiffrées, sur un support hors de l'hébergement, restauration testée et chronométrée.
- [ ] Données réelles uniquement en production. Le développement et le test utilisent des données fictives.
- [ ] Fichiers audio importés : type vérifié sur le contenu et non sur le nom, taille plafonnée, stockés hors de `public/`, supprimés selon une durée de conservation écrite.

## 6. Modèles de langue
Sans objet si l'application n'appelle aucun modèle.
- [ ] La sortie d'un modèle est une donnée non fiable. Elle est validée comme une saisie utilisateur.
- [ ] Appel de fonctions : liste blanche, paramètres validés côté serveur, exécution avec les droits de l'utilisateur connecté.
- [ ] Une action irréversible ou sortante demande une confirmation humaine. Supprimer une ligne d'offre se rattrape : une annulation peut remplacer la confirmation, selon les tests d'utilisation. Envoyer une offre au client est sortant : confirmation.
- [ ] Le texte libre (voix, documents, courriels) peut contenir des instructions : il ne donne jamais de droit.
- [ ] Fournisseur, lieu de traitement, conservation et entraînement vérifiés dans ses conditions, puis écrits ici.

## 7. Preuve automatique
- [ ] `scripts/securite/sonde-exposition.sh` tourne à chaque déploiement avec ses fichiers témoins. Une sonde rouge bloque la suite.
- [ ] `SONDE_TEMOINS=aucun` n'est utilisé qu'avec sa raison écrite ici (pile sans `.htaccess`, par exemple).
- [ ] Le test d'isolation entre clients, l'audit et les tests tournent en CI à chaque PR.
- [ ] Avant la première mise en production, cette liste est relue ligne à ligne, avec `owasp-security` et `conformite-mandats-clients`.
- [ ] Quand l'application tourne chez 4 à 5 artisans, un prestataire indépendant réalise un test d'intrusion avec comptes de deux clients et relit le code de connexion, de droits et d'isolation. Les failles critiques et hautes sont corrigées, puis contrôlées à nouveau. Report décidé par Xavier le 2026-11-06 (entorse inscrite au roadmap).
- [ ] D'ici là, Fable relit à part la connexion, les droits, le filtre par client et les appels de fonctions, et prouve chaque faille par un test qui échoue.
