---
chantier: De la voix à l'offre, 12 générer l'offre
slug: 2026-11-07_voix-offre/12-generer-offre
phase: 8
statut: gate_ouvert
gate_attendu: prod
profil: .claude/atoz-profile.md
---

## Prochaine action

Recette de Xavier. Ensuite : commit et PR sur son accord (sous-chantiers 01, 10, 11 et 12).

## En attente de toi

- Recette : « Nouvelle offre » en fenêtre, offre retravaillée puis quittée, client, réglages de l'en-tête, « Générer l'offre » et le PDF.
- Accord pour commiter la branche et ouvrir la PR.

## Journal

- 2026-11-12 : retour de recette de Xavier sur 11 : « Quand je clique nouvelle offre [...] ça devrait ouvrir une petite image » ; « l'offre elle n'est pas sauvegardée [...] il faut que je puisse retravailler une offre » ; « je veux pouvoir qu'elle me génère une offre. Simple, clair, efficace » ; réglages. Ajout d'un produit par la voix : plus tard, à sa demande.
- 2026-11-12 : « pas sauvegardée » reproduit avant correction : quantité tapée puis retour ou onglet sans quitter le champ, correction perdue (envoi du formulaire annulé par la navigation). Avec Entrée ou Tab : gardée.
- 2026-11-12 : défaut trouvé en route, antérieur : sous php -S, une variable posée au lancement perdait contre le .env (Conteneur::lireEnvironnement), contrairement à son commentaire. Test écrit, vu rouge, corrigé.
- 2026-11-12 : Ollama fermé par Xavier pendant le travail ; parcours cliqué joué sur un second serveur à IA simulée (port 8001), arrêté ensuite. L'extraction du client par Qwen n'est donc pas encore vérifiée sur le vrai modèle.

## Décisions

| Date | Décision | Raison |
|---|---|---|
| 2026-11-12 | Une correction de ligne part par fetch avec keepalive, et l'offre se met à jour sans recharger ; filet au départ de la page. | Un envoi classique est annulé par le lien touché juste après. |
| 2026-11-12 | PDF par dompdf 3.1.6, verrouillé (aucune ressource distante, pas de PHP, fichiers sous templates/). | « Je clique, ça génère une offre » ; l'envoi par courriel aura besoin du même PDF. Nouvelle dépendance, à inscrire au roadmap. |
| 2026-11-12 | Numéro d'offre propre à chaque artisan (1, 2, 3…), index unique (artisan_id, numero). | L'identifiant interne est commun à tous les clients : il sauterait de 14 à 27 sur un document client. |
| 2026-11-12 | L'IA relève le client et son lieu dans la dictée ; l'artisan les corrige en touchant l'en-tête de l'offre. | Le document doit sortir rempli sans rien taper. |
| 2026-11-12 | Réglages : adresse, téléphone, courriel, IDE (CHE-123.456.789), validité, conditions de paiement. | En-tête et conditions du document. |

## Preuves

```
$ vendor/bin/phpunit
OK (198 tests, 730 assertions)
$ vendor/bin/phpstan analyse
 [OK] No errors
$ composer audit
No security vulnerability advisories found.

Avant la correction (port 8000) :
retour « Offres » sans quitter le champ : tapé 51, relu 42 => PERDU
onglet Catalogue sans quitter le champ : tapé 52, relu 42 => PERDU
Après (port 8001, IA simulée) :
  tapé 61 puis retour « Offres » sans quitter le champ : relu 61 => gardé
  tapé 62 puis onglet Catalogue sans quitter le champ : relu 62 => gardé
  tapé 63 puis Générer l'offre sans quitter le champ : relu 63 => gardé
  pendant le premier envoi, prix en cours de frappe : 31.50
  relu : quantité 7, prix 31.50

ConteneurTest::testUneVariableDuSystemeLEmporteSurLeFichierEnv, avant correction : Failed asserting that two strings are identical.

  après « Nouvelle offre », adresse : / ; boutons : Parler, Importer
  PDF : HTTP 200, application/pdf, inline; filename="Offre-1.pdf", 30940 octets, début %PDF-
  liste : ... Madame Exemple · Offre 1 · modifiée le 12.11.2026 14:47 CHF 4'346.15
  (4 020.50 × 0,081 = 325.66 ; 4 346.16 arrondi à 4 346.15)
```
