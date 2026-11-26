<?php

declare(strict_types=1);

namespace OffreGen\Tests\Bout;

use OffreGen\Audio\Traiteur;
use OffreGen\Catalogue\CatalogueFictif;
use OffreGen\Donnees\DepotEnregistrements;
use OffreGen\Donnees\DepotOffres;
use OffreGen\Donnees\DepotProduits;
use OffreGen\Donnees\Portee;
use OffreGen\Donnees\Statut;
use OffreGen\Http\Conteneur;
use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Dossiers;
use OffreGen\Tests\Outils\Jeu;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\NativeClock;

/**
 * De bout en bout sur les vrais services locaux : Whisper (outils/ia-locale/demarrer.sh) et Ollama.
 * Hors de la suite par défaut : vendor/bin/phpunit --group ia-locale
 * Dictée fictive, voix de synthèse « Hortense » de Windows, 28 s : tests/fixtures/audio/dictee-peintre.ogg.
 */
#[Group('ia-locale')]
final class IaLocaleTest extends TestCase
{
    private const UUID = '33333333-3333-4333-8333-333333333333';

    public function testLaDicteeDuPeintreDevientLOffreAttendue(): void
    {
        $jeu = new Jeu();
        $dossiers = new Dossiers();
        try {
            $reglages = array_intersect_key(
                Conteneur::lireEnvironnement(dirname(__DIR__, 2)),
                array_flip(['TRANSCRIPTION', 'TRANSCRIPTION_URL', 'TRANSCRIPTION_PRODUIT', 'TRANSCRIPTION_CLE', 'TRANSCRIPTION_MODELE',
                    'EXTRACTION', 'EXTRACTION_URL', 'EXTRACTION_CLE', 'EXTRACTION_MODELE', 'EXTRACTION_SANS_RAISONNEMENT']),
            );
            self::assertSame(['api', 'api'], [$reglages['TRANSCRIPTION'] ?? '', $reglages['EXTRACTION'] ?? ''], 'Régler TRANSCRIPTION=api et EXTRACTION=api dans .env');
            $c = new Conteneur(dirname(__DIR__, 2), BaseDeTest::env() + $reglages + $dossiers->env(), new NativeClock('UTC'), BaseDeTest::pdo());

            $portee = new Portee($jeu->a);
            (new DepotProduits($jeu->base, $portee))->remplacerTout(CatalogueFictif::produits('peintre'));
            mkdir($dossiers->env()['AUDIO_DOSSIER'] . "/{$jeu->a}", 0700, true);
            copy(dirname(__DIR__) . '/fixtures/audio/dictee-peintre.ogg', $dossiers->env()['AUDIO_DOSSIER'] . "/{$jeu->a}/" . self::UUID . '.ogg');
            $id = (new DepotEnregistrements($jeu->base, $portee))->creer(self::UUID, 'dictee-peintre.ogg', 'audio/ogg', 1, "{$jeu->a}/" . self::UUID . '.ogg', new \DateTimeImmutable('now', new \DateTimeZone('UTC')));

            $debut = time();
            do {
                $statut = $c->traiteur()->avancer(self::UUID);
                if ($statut !== Statut::PRET && $statut !== Statut::ECHEC) {
                    sleep(2);
                }
            } while ($statut !== Statut::PRET && $statut !== Statut::ECHEC && time() - $debut < 240);
            $erreur = BaseDeTest::ligne("SELECT erreur FROM enregistrement WHERE uuid = '" . self::UUID . "'")['erreur'];
            self::assertSame(Statut::PRET, $statut, 'Erreur notée : ' . (is_string($erreur) ? $erreur : ''));
            fwrite(STDERR, sprintf("\n[ia-locale] offre prête en %d s\n", time() - $debut));

            $offres = new DepotOffres($jeu->base, $portee);
            $offreId = $offres->idParEnregistrement($id);
            self::assertNotNull($offreId);
            $trouve = [];
            foreach ($offres->lignes($offreId) as $l) {
                $trouve[$l['designation']] = [$l['quantite_milliemes'], $l['a_verifier']];
                fwrite(STDERR, sprintf("[ia-locale] %s : %s, à vérifier %d, entendu « %s »\n", $l['designation'], $l['quantite_milliemes'] / 1000, $l['a_verifier'], $l['entendu'] ?? ''));
            }
            // Attendu, d'après la dictée : quantités exactes ; « environ », « à peu près », « une douzaine » et le prix manquant à vérifier.
            $attendu = [
                'Protection des sols et des meubles' => 1000,
                'Peinture des murs, 2 couches' => 42000,
                'Peinture des plafonds, 2 couches' => 18000,
                'Laquage de porte, 2 faces' => 3000,
                'Peinture anti-moisissure' => 12000,
                'Déplacement' => 1000,
            ];
            foreach ($attendu as $designation => $milliemes) {
                self::assertArrayHasKey($designation, $trouve, "Ligne manquante : $designation");
                self::assertSame($milliemes, $trouve[$designation][0], "Quantité de « $designation »");
            }
            self::assertSame(1, $trouve['Peinture anti-moisissure'][1], 'Prix manquant : la ligne doit être à vérifier');
            self::assertSame(1, $trouve['Peinture des murs, 2 couches'][1], '« environ » : la ligne doit être à vérifier');
            self::assertCount(6, $trouve, 'Une ligne de trop : ' . implode(', ', array_keys(array_diff_key($trouve, $attendu))));
        } finally {
            $dossiers->effacer();
        }
    }
}
