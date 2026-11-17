---
chantier: Mise en production d'offreGen
slug: 2026-11-17_mise-en-production
phase: 4
statut: en_cours
gate_attendu: aucun
profil: .claude/atoz-profile.md
---

## Prochaine action

Étape 1 du `PLAN.md` : remettre à jour les `ETAT.md` en retard et le roadmap, puis écrire la recette regroupée.

## En attente de toi

- Dire sous quel domaine ouvrir la production, et avec quelle adresse l'application enverra ses courriels (mot de passe oublié, alertes).
- Confirmer que la première production est un essai gratuit, sans paiement, avec des comptes que tu crées.
- Dire si tu veux la relecture de la connexion et de l'isolation par Fable (prévue dans `docs/SECURITE.md`), en plus de la revue par Opus.
- Dire s'il faut créer la tâche Origin de ce chantier.

## Suivi Origin

(à créer, sur accord de Xavier)

## Décisions

- 2026-11-17, Xavier : « il faut que tu prépares le test […] tu vas relister tout ce qui n'a pas été fait, tout ce qui pose problème. Et ensuite, on va travailler pour faire en sorte qu'on puisse passer en production le plus vite possible. En respectant et faisant les choses bien. »
- 2026-11-17, Xavier : formules d'abonnement, la recharge pour les abonnés est gardée, le pack sans abonnement est écarté (« ça casse le système »).

## Journal

- 2026-11-17 : inventaire (`INVENTAIRE.md`) établi d'après les `ETAT.md` de chaque sous-chantier, `docs/DEPLOIEMENT.md`, `docs/SECURITE.md`, la crontab du site de test, les routes et la liste des PR. Plan en neuf étapes (`PLAN.md`).

## Preuves

```
$ ls .github/workflows : No such file or directory
$ git log --oneline main..chantier-voix-offre | wc -l : 77
$ gh pr list : #3 « De la voix à l'offre… » OPEN depuis le 2026-11-12
$ crontab -l (site de test) : seulement bin/traiter-file, pas de bin/purger-audio
$ grep routes : connexion, déconnexion ; ni mot de passe oublié ni changement de mot de passe
```
