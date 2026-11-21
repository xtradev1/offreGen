<?php

declare(strict_types=1);

namespace OffreGen\Tests\Donnees;

use OffreGen\Tests\Outils\BaseDeTest;
use PHPUnit\Framework\TestCase;

/**
 * Modèles du bâtiment (docs/plans/2026-11-19_modeles-offre/PLAN.md, lot 3) : le chantier, le début et la durée des
 * travaux, ce qui n'est pas compris et la date de la visite sur l'offre ; le nom du contact sur le compte.
 */
final class ColonnesChantierTest extends TestCase
{
    public function testLesColonnesDuChantierExistentEtAcceptentNull(): void
    {
        $requete = BaseDeTest::pdo()->query(
            "SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND (
                 (TABLE_NAME = 'offre' AND COLUMN_NAME IN ('chantier_adresse', 'chantier_contact', 'chantier_acces', 'debut_travaux', 'duree_travaux', 'non_compris', 'visite_le'))
                 OR (TABLE_NAME = 'artisan' AND COLUMN_NAME IN ('contact_nom', 'lettre_appel', 'lettre_offre_debut', 'lettre_offre_fin', 'lettre_facture_debut', 'lettre_facture_fin')))"
        );
        self::assertNotFalse($requete);
        $colonnes = [];
        foreach ($requete->fetchAll(\PDO::FETCH_ASSOC) as $c) {
            $colonnes[$c['TABLE_NAME'] . '.' . $c['COLUMN_NAME']] = [$c['COLUMN_TYPE'], $c['IS_NULLABLE'], $c['COLUMN_DEFAULT']];
        }
        ksort($colonnes);
        // Une offre et un compte d'avant la migration : tout est vide, l'ancienne version du code n'écrit rien ici.
        self::assertSame([
            'artisan.contact_nom' => ['varchar(120)', 'YES', 'NULL'],
            // Phrases de Lettre (2026-11-20) : vides, la lettre garde les phrases d'aujourd'hui.
            'artisan.lettre_appel' => ['varchar(8)', 'YES', 'NULL'],
            'artisan.lettre_facture_debut' => ['varchar(500)', 'YES', 'NULL'],
            'artisan.lettre_facture_fin' => ['varchar(500)', 'YES', 'NULL'],
            'artisan.lettre_offre_debut' => ['varchar(500)', 'YES', 'NULL'],
            'artisan.lettre_offre_fin' => ['varchar(500)', 'YES', 'NULL'],
            'offre.chantier_acces' => ['varchar(255)', 'YES', 'NULL'],
            'offre.chantier_adresse' => ['varchar(255)', 'YES', 'NULL'],
            'offre.chantier_contact' => ['varchar(190)', 'YES', 'NULL'],
            'offre.debut_travaux' => ['varchar(80)', 'YES', 'NULL'],
            'offre.duree_travaux' => ['varchar(80)', 'YES', 'NULL'],
            'offre.non_compris' => ['varchar(1000)', 'YES', 'NULL'],
            'offre.visite_le' => ['date', 'YES', 'NULL'],
        ], $colonnes);
    }
}
