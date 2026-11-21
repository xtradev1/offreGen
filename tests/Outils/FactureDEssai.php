<?php

declare(strict_types=1);

namespace OffreGen\Tests\Outils;

use OffreGen\Adresse\Adresse;
use OffreGen\Compte\Marque;
use OffreGen\Donnees\Portee;
use OffreGen\Http\Conteneur;
use OffreGen\Http\Controleur\FactureControleur;
use OffreGen\Http\Controleur\OffreControleur;
use Symfony\Component\HttpFoundation\Request;

/**
 * Une facture fabriquée pour les tests du document : un brouillon numéroté de l'artisan A, sur l'offre du jeu, avec le
 * nombre de lignes voulu, et le HTML que lit dompdf pour elle. L'artisan reçoit une adresse et un IBAN : la section
 * de paiement en dépend.
 */
final class FactureDEssai
{
    public const IBAN = 'CH9300762011623852957';

    /**
     * @param 'essai'|'bas'|'apart'|null $place où va la section de paiement (FactureControleur::rendrePdf) ; null : sans section
     * @param array<string, mixed> $plus variables de plus pour le gabarit
     * @param bool $annulee la facture annulée le 2026-11-08, telle que FactureControleur::annuler la rend : sans section
     */
    public static function html(Conteneur $c, Jeu $jeu, Marque $m, int $lignes, ?string $place, array $plus = [], bool $annulee = false): string
    {
        BaseDeTest::pdo()->exec("UPDATE artisan SET entreprise = 'Peinture Morel Sàrl', iban = '" . ($place === null ? '' : self::IBAN) . "', adresse_rue = 'Rue de l''Exemple',
            adresse_numero = '12', adresse_npa = '2800', adresse_localite = 'Delémont', adresse_pays = 'CH', ide = 'CHE-123.456.789' WHERE id = {$jeu->a}");
        BaseDeTest::pdo()->exec("DELETE FROM facture_ligne WHERE artisan_id = {$jeu->a}");
        BaseDeTest::pdo()->exec("DELETE FROM facture WHERE artisan_id = {$jeu->a}");
        $depot = $c->factures(new Portee($jeu->a));
        $date = new \DateTimeImmutable('2026-11-07');
        $copies = [];
        foreach (OffreDEssai::lignes($lignes) as $l) {
            $copies[] = ['designation' => $l['designation'], 'quantite_milliemes' => $l['quantite_milliemes'], 'unite' => $l['unite'], 'prix_centimes' => $l['prix_centimes'],
                'tva_pour_dix_mille' => null, 'categorie' => $l['categorie'], 'produit_id' => null];
        }
        $id = $depot->creer([
            'offre_id' => $jeu->offre['a'], 'numero' => '2026-001', 'genre' => 'finale', 'acompte_rang' => null, 'acompte_nombre' => null, 'acompte_pourcent' => null,
            'date_facture' => $date, 'echeance' => $date->modify('+30 days'), 'titre' => "Peinture complète de l'appartement",
            'client_nom' => 'Famille Exemple', 'client_adresse' => "Chemin des Essais 4\n1700 Fribourg",
            'client_postale' => new Adresse('Chemin des Essais', '4', '1700', 'Fribourg'), 'refaite_depuis' => null,
        ], $copies, $jeu->maintenant);
        if ($annulee) {
            BaseDeTest::pdo()->exec("UPDATE facture SET statut = 'annulee', annulee_le = '2026-11-08 09:00:00' WHERE id = $id");
            $plus = ['annulee' => true, 'qr' => null] + $plus;
            $place = null;
        }
        $f = $depot->parId($id) ?? throw new \LogicException('Facture introuvable.');
        $requete = Request::create('/');
        $requete->attributes->set('_artisan', $jeu->a);
        $vue = (new FactureControleur($c))->donnees($requete, $f) + ['m' => $m, 'logo_src' => null];
        return OffreControleur::htmlPdf($c, ($place === null ? [] : ['qr_place' => $place]) + $plus + $vue);
    }
}
