-- Offre de mesure inventée (chantier « sans rechargement », 2026-11-23) : 30 lignes en quatre pièces, dont
-- 4 « À vérifier », 2 sans prix, 2 à prix dicté, 1 option, 1 variante. Offre 3 (rien d'envoyé), offre 4 (envoyée,
-- pour « Facturer »), offre 5 (archivée, pour « Restaurer »). Compte 1 de offregen_fluidite_vue.
DELETE FROM ligne WHERE offre_id IN (3, 4, 5);
DELETE FROM offre WHERE id IN (3, 4, 5);
INSERT INTO offre (id, artisan_id, numero, titre, client_id, cree_le, exemple, offre_adressee, factures_adressees)
VALUES (3, 1, 3, 'Appartement Rue du Marché, rénovation complète', 3, NOW() - INTERVAL 1 HOUR, 0, 'client', 'client');
INSERT INTO offre (id, artisan_id, numero, titre, client_id, cree_le, envoyee_le, exemple, offre_adressee, factures_adressees)
VALUES (4, 1, 4, 'Cage d''escalier, immeuble Avenue de la Gare', 4, NOW() - INTERVAL 3 DAY, NOW() - INTERVAL 2 DAY, 0, 'client', 'client');
INSERT INTO offre (id, artisan_id, numero, titre, client_id, cree_le, archivee_le, exemple, offre_adressee, factures_adressees)
VALUES (5, 1, 5, 'Volets, Chemin des Vignes', 5, NOW() - INTERVAL 20 DAY, NOW() - INTERVAL 5 DAY, 0, 'client', 'client');

INSERT INTO ligne (artisan_id, offre_id, rang, designation, quantite_milliemes, unite, prix_centimes, produit_id, piece, a_verifier, entendu, prix_dicte_centimes, nature, remplace_id, retenue)
SELECT 1, 3, r.rang, p.designation, r.q, p.unite, IF(r.sans_prix, 0, p.prix_centimes), p.id, r.piece, r.verif, r.entendu, r.dicte, 'ligne', NULL, 0
FROM (
  SELECT 1 rang, 1 n, 1000 q, NULL piece, 0 verif, NULL entendu, NULL dicte, 0 sans_prix UNION ALL
  SELECT 2, 11, 1000, NULL, 0, NULL, NULL, 0 UNION ALL
  SELECT 3, 13, 24000, 'Séjour', 0, NULL, NULL, 0 UNION ALL
  SELECT 4, 15, 48000, 'Séjour', 0, NULL, NULL, 0 UNION ALL
  SELECT 5, 4, 48000, 'Séjour', 1, 'les murs du séjour deux couches quarante-huit mètres', NULL, 0 UNION ALL
  SELECT 6, 5, 24000, 'Séjour', 0, NULL, 3000, 0 UNION ALL
  SELECT 7, 6, 18000, 'Séjour', 0, NULL, NULL, 0 UNION ALL
  SELECT 8, 7, 2000, 'Séjour', 0, NULL, NULL, 0 UNION ALL
  SELECT 9, 40, 6000, 'Séjour', 0, NULL, NULL, 0 UNION ALL
  SELECT 10, 60, 3000, 'Séjour', 1, 'les radiateurs à la laque trois pièces', NULL, 0 UNION ALL
  SELECT 11, 13, 14000, 'Chambre', 0, NULL, NULL, 0 UNION ALL
  SELECT 12, 4, 36000, 'Chambre', 0, NULL, NULL, 0 UNION ALL
  SELECT 13, 5, 14000, 'Chambre', 0, NULL, NULL, 0 UNION ALL
  SELECT 14, 6, 14000, 'Chambre', 0, NULL, NULL, 0 UNION ALL
  SELECT 15, 7, 1000, 'Chambre', 0, NULL, NULL, 0 UNION ALL
  SELECT 16, 150, 36000, 'Chambre', 1, 'du papier peint sur un mur de la chambre', NULL, 0 UNION ALL
  SELECT 17, 8, 12000, 'Chambre', 0, NULL, NULL, 1 UNION ALL
  SELECT 18, 13, 12000, 'Cuisine', 0, NULL, NULL, 0 UNION ALL
  SELECT 19, 4, 30000, 'Cuisine', 0, NULL, 3200, 0 UNION ALL
  SELECT 20, 5, 12000, 'Cuisine', 0, NULL, NULL, 0 UNION ALL
  SELECT 21, 70, 12000, 'Cuisine', 0, NULL, NULL, 0 UNION ALL
  SELECT 22, 30, 8000, 'Cuisine', 1, 'ponçage des boiseries de la cuisine', NULL, 0 UNION ALL
  SELECT 23, 13, 9000, 'Salle de bain', 0, NULL, NULL, 0 UNION ALL
  SELECT 24, 8, 9000, 'Salle de bain', 0, NULL, NULL, 1 UNION ALL
  SELECT 25, 5, 9000, 'Salle de bain', 0, NULL, NULL, 0 UNION ALL
  SELECT 26, 7, 1000, 'Salle de bain', 0, NULL, NULL, 0 UNION ALL
  SELECT 27, 160, 9000, 'Salle de bain', 0, NULL, NULL, 0 UNION ALL
  SELECT 28, 12, 6000, NULL, 0, NULL, NULL, 0 UNION ALL
  SELECT 29, 200, 1000, NULL, 0, NULL, NULL, 0 UNION ALL
  SELECT 30, 250, 1000, NULL, 0, NULL, NULL, 0
) r JOIN (SELECT p.*, ROW_NUMBER() OVER (ORDER BY id) n FROM produit p WHERE artisan_id = 1) p ON p.n = r.n;

-- Une option et une variante (de la ligne 12, murs de la chambre).
INSERT INTO ligne (artisan_id, offre_id, rang, designation, quantite_milliemes, unite, prix_centimes, produit_id, piece, nature, remplace_id, retenue)
SELECT 1, 3, 31, designation, 1000, unite, prix_centimes, id, 'Chambre', 'option', NULL, 0 FROM produit WHERE artisan_id = 1 ORDER BY id LIMIT 1 OFFSET 230;
INSERT INTO ligne (artisan_id, offre_id, rang, designation, quantite_milliemes, unite, prix_centimes, produit_id, piece, nature, remplace_id, retenue)
SELECT 1, 3, 32, p.designation, 36000, p.unite, p.prix_centimes, p.id, 'Chambre', 'variante', l.id, 0
FROM produit p JOIN ligne l ON l.offre_id = 3 AND l.rang = 12 WHERE p.artisan_id = 1 ORDER BY p.id LIMIT 1 OFFSET 100;

-- Offre envoyée (12 lignes justes) et offre archivée (4 lignes).
INSERT INTO ligne (artisan_id, offre_id, rang, designation, quantite_milliemes, unite, prix_centimes, produit_id, nature, retenue)
SELECT 1, 4, ROW_NUMBER() OVER (ORDER BY id), designation, 20000, unite, prix_centimes, id, 'ligne', 0 FROM produit WHERE artisan_id = 1 AND id BETWEEN 1 AND 12;
INSERT INTO ligne (artisan_id, offre_id, rang, designation, quantite_milliemes, unite, prix_centimes, produit_id, nature, retenue)
SELECT 1, 5, ROW_NUMBER() OVER (ORDER BY id), designation, 8000, unite, prix_centimes, id, 'ligne', 0 FROM produit WHERE artisan_id = 1 AND id BETWEEN 100 AND 103;

SELECT offre_id, COUNT(*) lignes, SUM(a_verifier) a_verifier, SUM(prix_centimes = 0) sans_prix, SUM(prix_dicte_centimes IS NOT NULL) prix_dicte,
       SUM(nature = 'option') options, SUM(nature = 'variante') variantes FROM ligne WHERE offre_id IN (3, 4, 5) GROUP BY offre_id;
