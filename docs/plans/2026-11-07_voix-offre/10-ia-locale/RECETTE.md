# Recette du sous-chantier 10 : la vraie IA, en local

Tout reste sur ton PC. La transcription tourne dans WSL, l'extraction dans ton Ollama sous Windows.
Rien ne part chez Infomaniak ni ailleurs.

## Préparer (une fois, déjà fait le 2026-11-12)

Le Whisper local est installé dans `~/offregen-ia`, la variante `offregen-qwen3.8` existe dans
Ollama, et le `.env` contient `TRANSCRIPTION=api` et `EXTRACTION=api`.

## Lancer

Deux terminaux Ubuntu, dans `~/offreGen` :

```
outils/ia-locale/demarrer.sh
```

```
php -d upload_max_filesize=200M -d post_max_size=210M -S 127.0.0.1:8000 -t public public/index.php
```

Ollama doit tourner sous Windows. Vérification dans un troisième terminal : `bin/verifier-ia`.

## Tester

| # | Geste | Attendu |
|---|---|---|
| 1 | Enregistre 1 à 2 minutes avec l'enregistreur de ton téléphone, en peintre, sans client ni nom réel : « salon, murs, environ trente mètres carrés, deux couches ; plafond vingt mètres carrés ; deux portes à laquer ; un déplacement » | un fichier m4a (iPhone) ou m4a, 3gp, amr (Android) |
| 2 | Transfère-le sur le PC | |
| 3 | http://localhost:8000, connecte-toi avec `demo-peintre@exemple.ch` (mot de passe : `cat ~/.offregen-demo-compte`) | « Mes offres » |
| 4 | « Nouvelle offre », choisis ton fichier | la page « Préparation de l'offre » arrive après la transcription et l'extraction (environ 30 s pour une minute de dictée) |
| 5 | « Voir l'offre » | une ligne par travail dicté, avec le produit du catalogue, ta quantité, et sous la ligne ce que l'application a entendu ; « À vérifier » sur « environ » |
| 6 | Relis chaque ligne | note ce qui est faux : mot mal entendu, mauvais produit, quantité, ligne en trop ou manquante |

Au retour, dis-moi pour chaque essai : le format et la durée du fichier, le temps d'attente, et les
lignes fausses. Ne m'envoie ni l'audio ni la transcription si tu as parlé d'un vrai client.

## Si ça bloque

- `bin/verifier-ia` dit « injoignable » pour la transcription : `outils/ia-locale/demarrer.sh` n'est pas lancé.
- Il le dit pour l'extraction : Ollama n'est pas lancé sous Windows.
- Une dictée longue (plus de 5 minutes) : la page reste « en préparation » ; la reprise toutes les 30 s
  la termine, la page se rafraîchit seule.
- Plus de 40 minutes de dictée dépassent le contexte local de Qwen : l'offre échoue. Chez Infomaniak, la limite sera la leur.
