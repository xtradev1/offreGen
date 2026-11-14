# Recette du sous-chantier 01 : socle et import

Tout se passe sur ton PC, en local. Rien n'est en ligne, aucune donnée ne sort de WSL.
La transcription et l'extraction sont factices : l'offre affichée est toujours la même liste
tirée du catalogue, quel que soit ce que tu dis dans l'enregistrement.

## Préparer

1. Dans un terminal Ubuntu :

   ```
   cd ~/offreGen
   bin/reinitialiser-demo demo-peintre@exemple.ch peintre
   php -d upload_max_filesize=200M -d post_max_size=210M -S 127.0.0.1:8000 -t public public/index.php
   ```

2. Le mot de passe du compte démo : `cat ~/.offregen-demo-compte` dans un deuxième terminal Ubuntu.
3. Sur ton téléphone, enregistre 30 secondes avec l'enregistreur habituel, sans client et sans nom réel
   (« salon, douze mètres carrés de murs… »). Transfère le fichier sur le PC.

## Tester

| # | Geste | Attendu |
|---|---|---|
| 1 | Ouvre http://localhost:8000 | Page « Connexion » |
| 2 | Connecte-toi avec un mauvais mot de passe | « Courriel ou mot de passe incorrect. » |
| 3 | Connecte-toi avec `demo-peintre@exemple.ch` et le bon mot de passe | « Mes offres », « Peinture Morel Sàrl (compte de démonstration) » |
| 4 | « Nouvelle offre », choisis un fichier qui n'est pas un audio (un PDF ; dans le sélecteur, prends « Tous les fichiers ») | Message rouge « pas un enregistrement audio reconnu » |
| 5 | Choisis le fichier de ton téléphone | L'envoi part seul, la page « Préparation de l'offre » arrive avec trois étapes cochées |
| 6 | « Voir l'offre » | Trois postes, le troisième « À vérifier », totaux HT, TVA 8,1 %, TTC |
| 7 | « Offres » en haut à gauche | L'offre dans la liste, avec son total |
| 8 | « Se déconnecter » en bas de la liste | Retour à la connexion |

Note le format du fichier de ton téléphone (extension) et sa taille : c'est le premier vrai fichier
que l'application aura vu.

## Si ça bloque

- Après cinq mauvais mots de passe en 15 minutes, la connexion est bloquée un quart d'heure : c'est voulu.
- Une page d'erreur 500 : copie-moi la fin de `~/offreGen/var/log/app.log`.
- Captures de référence du parcours : `captures/` à côté de ce fichier.
