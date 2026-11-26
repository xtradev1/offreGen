# REVUE : prospects

Revue du 2026-11-24. Intensité : lourde, le diff touche les droits par route et le filtre par rôle. Trois relecteurs `route-heavy` indépendants (correction, sécurité avec la grille OWASP, régression), en lecture seule. Chaque trouvaille a été relue dans le code avant son verdict ; les neuf retenues ont un test rouge avant correction dans `tests/Compte/ProspectsRevueTest.php` ou `tests/Http/ProspectsEcransTest.php`.

| Trouvaille | Lentille | Gravité | Verdict | Suite |
|---|---|---|---|---|
| S2. Le plancher de trois lettres se contournait (« a e i ») : un commercial listait les comptes de ses collègues, vingt par requête | Sécurité | importante | retenu | un mot de trois lettres au moins ; les comptes ne se cherchent que par ces mots (`Prospects::chercher`) |
| S3. Un courriel deviné faisait dire à l'avertissement de doublon quel compte le porte | Sécurité | importante | retenu | les comptes ne se rapprochent plus que par le nom (`DepotProspects::comptesRessemblants`) |
| C1 et S5. La fusion retirait une fiche à son suiveur, ou donnait le compte de l'un à l'autre ; une fiche liée sans suiveur devenait prenable | Correction, sécurité | importante | retenu | deux fiches suivies par deux personnes ne se fusionnent pas (422, la phrase le dit) ; une fiche libre qui absorbe une fiche tenue passe à celui qui tenait l'autre ; le formulaire « Je prends » existe pour toute fiche libre |
| C2 et S7. Deux envois rapprochés de « Créer une démo » laissaient une démo liée à rien | Correction, sécurité | importante | retenu | `lierCompte` dit s'il a écrit ; sinon la transaction annule la seconde démo |
| R1. Les fiches d'un membre retiré de l'équipe restaient tenues par lui : gelées | Régression | importante | retenu | « tenue » exige que le suiveur soit encore dans l'équipe ; ses fiches sont libres, « suivi avant par » |
| C3. Perdre la course à « Je prends » donnait une page introuvable | Correction | importante | retenu | la fiche se rouvre avec « Ce prospect est déjà suivi » ; une fiche inconnue reste en 404 |
| C4. L'échéance se comptait sur le jour UTC : une action après minuit à Zurich perdait un jour | Correction | mineure | retenu | jour lu deux heures après l'heure UTC, à une heure près en faveur de celui qui suit ; « libre depuis » lu à Zurich |
| C6. La fusion copiait le numéro postal d'une fiche sur la localité de l'autre | Correction | mineure | retenu | le lieu de la fiche gardée reste entier |
| C7. Le prochain pas saisi disparaissait sans un mot quand « Je m'en occupe » était décoché | Correction | mineure | retenu | refus, avec la phrase qui dit quoi faire |
| R3. Le détail du journal coupé à 250 caractères pour une colonne de 190 : fusion annulée sur deux noms longs | Régression | mineure | retenu | coupe à 190 |
| R5. Le commentaire du menu ne citait pas « prospects » | Régression | mineure | retenu | corrigé |
| S1. La fiche d'un collègue montre la date de sa dernière action et le jour où elle redevient libre : on en déduit la date de son prochain pas | Sécurité | importante | écarté | ces deux dates sont sur l'écran 5 de la maquette, validé par Xavier (P2 = A) ; c'est ce qui permet aux collègues de s'organiser. Signalé à Xavier |
| S4. L'avertissement de doublon confirme qu'un téléphone ou un courriel tapé est celui d'une fiche d'un collègue | Sécurité | importante | écarté | c'est le principe du dédoublonnage, validé (P4, écran 8) ; la personne connaît déjà le numéro qu'elle tape. Signalé à Xavier |
| S6. La sorte de compte (démo, essai, client) se lit sur la fiche d'un collègue | Sécurité | mineure | écarté | sans elle, un client de Dicta serait démarché ; décision notée dans `ETAT.md`, à confirmer par Xavier avec l'exception à la règle du 19.11 |
| C5 et R2. La suppression d'une démo finie détache la fiche, qui devient libre à une date ancienne et perd la trace de la démo | Correction, régression | mineure | hors périmètre | la démo est finie depuis 30 jours sans suite : la fiche libre est le bon état. Garder la trace demanderait de toucher `DepotDemosFinies` (autre chantier) |
| C8. Le pas d'une fiche liée à un compte reste « en retard » tant que personne ne touche « C'est fait » | Correction | mineure | écarté | c'est le rôle d'un pas ; l'écran 10 de la maquette en montre un sur une fiche en démo |
| S7 (seconde partie). Le plafond de 30 se dépasse par deux requêtes simultanées du même commercial | Sécurité | mineure | écarté | dépassement d'une unité, sans effet : la liste ne donne aucun droit |
| R4. `BaseDeTest::vider` échoue sur une base de test non migrée | Régression | mineure | écarté | toute base de test se migre avant la suite, comme pour chaque table ajoutée jusqu'ici |

Écartés par les relecteurs eux-mêmes après lecture : injection SQL (tout est paramétré), XSS (aucun `|raw`, `tel:` filtré), CSRF (vérifié par le noyau pour tout POST), cache (`no-store`, `/admin` exclu du service worker), paramètres nommés répétés, menu à cinq entrées, collisions de classes CSS, migration additive et son retour arrière.

Tests absents, notés par le relecteur de correction : aucune course réelle entre deux requêtes n'est jouée (deux « Je prends », deux « Créer une démo »). Les garde-fous sont dans le SQL (`UPDATE … WHERE NOT tenue`, `WHERE compte_id IS NULL`) et testés un à la fois.
