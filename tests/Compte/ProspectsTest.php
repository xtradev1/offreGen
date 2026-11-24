<?php

declare(strict_types=1);

namespace OffreGen\Tests\Compte;

use OffreGen\Compte\DoublonProspect;
use OffreGen\Compte\Prospects;
use OffreGen\Compte\Role;
use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Les règles du catalogue des prospects (docs/plans/2026-11-24_prospects, réponses de Xavier du 2026-11-24) : chacun
 * prend lui-même, un collègue voit qu'une fiche est suivie sans en lire le téléphone ni les notes (P2 = A), les notes
 * suivent la fiche (P6 = A). Depuis le second message de Xavier du 2026-11-24 : une fiche reste suivie tant qu'on ne la
 * lâche pas, sans plafond (P3 = B et le plafond de 30 sont retirés « pour l'instant »).
 */
final class ProspectsTest extends TestCase
{
    private Jeu $jeu;
    private MockClock $horloge;
    private Prospects $prospects;
    /** Nina et Léo, commerciaux. */
    private int $nina;
    private int $leo;

    private const ROUX = ['entreprise' => 'Peinture Roux Sàrl', 'localite' => '2800 Delémont', 'metier' => 'peintre', 'metier_saisi' => '', 'telephone' => '079 555 01 42',
        'courriel' => 'marc@roux-peinture.example', 'contact' => 'Marc Roux', 'origine' => 'grossiste', 'ide' => '', 'pas_texte' => '', 'pas_date' => ''];

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        $this->nina = $this->jeu->a;
        $this->leo = $this->jeu->b;
        BaseDeTest::pdo()->exec("UPDATE artisan SET role = 'commercial', entreprise = 'Nina Rey' WHERE id = {$this->nina}");
        BaseDeTest::pdo()->exec("UPDATE artisan SET role = 'commercial', entreprise = 'Léo Favre' WHERE id = {$this->leo}");
        // Mardi 24 novembre 2026, 10 h à Zurich.
        $this->horloge = new MockClock('2026-11-24 09:00:00', 'UTC');
        $this->prospects = Monde::conteneur($this->horloge)->prospects();
    }

    /** @param array<string, string> $champs */
    private function creer(int $acteur, array $champs = [], bool $prend = true, bool $confirme = false): int
    {
        return $this->prospects->creer($acteur, $champs + self::ROUX, $prend, $confirme);
    }

    /** @return array<string, mixed> */
    private function vue(int $acteur, int $id, bool $toutVoir = false): array
    {
        return $this->prospects->vue($acteur, $toutVoir, $this->prospects->fiche($id));
    }

    public function testLesClesDeRapprochement(): void
    {
        self::assertSame('peinture roux', Prospects::cleNom('  Peinture ROUX Sàrl '));
        self::assertSame('platrerie chevre fils', Prospects::cleNom('Plâtrerie Chèvre & fils SA'));
        self::assertSame('0795550142', Prospects::cleTelephone('+41 79 555 01 42'));
        self::assertSame('0795550142', Prospects::cleTelephone('0041 (79) 555.01.42'));
        self::assertSame('0795550142', Prospects::cleTelephone('079 555 01 42'));
        self::assertSame('CHE-123.456.789', Prospects::ide('che 123 456 789 TVA'));
        self::assertNull(Prospects::ide('  '));
        $this->expectException(\InvalidArgumentException::class);
        Prospects::ide('CHE-12.345');
    }

    public function testCreerUneFicheLaDonneACeluiQuiLaSaisit(): void
    {
        $id = $this->creer($this->nina);
        $fiche = BaseDeTest::ligne("SELECT * FROM prospect WHERE id = $id");
        self::assertSame(['Peinture Roux Sàrl', 'peinture roux', '2800', 'Delémont', 'peintre', '0795550142', 'a_contacter'],
            [$fiche['entreprise'], $fiche['nom_cle'], $fiche['npa'], $fiche['localite'], $fiche['metier'], $fiche['telephone_cle'], $fiche['etat']]);
        self::assertSame($this->nina, (int) $fiche['suivi_par']);
        self::assertSame($this->nina, (int) $fiche['cree_par']);
        // Saisie sans la prendre : la fiche est libre.
        $libre = $this->creer($this->nina, ['entreprise' => 'Sols Berbier', 'localite' => 'Moutier', 'telephone' => '', 'courriel' => ''], false);
        self::assertNull(BaseDeTest::ligne("SELECT suivi_par, npa FROM prospect WHERE id = $libre")['suivi_par']);
        self::assertNull(BaseDeTest::ligne("SELECT npa FROM prospect WHERE id = $libre")['npa']);
    }

    public function testUneSaisieIncompleteEstRefusee(): void
    {
        foreach ([['entreprise' => ' '], ['localite' => ''], ['metier' => 'astronaute'], ['telephone' => '12'], ['courriel' => 'pas un courriel'], ['origine' => 'radio'],
            ['pas_texte' => 'Rappeler', 'pas_date' => ''], ['pas_texte' => 'Rappeler', 'pas_date' => '2026-11-20'], ['pas_texte' => 'Rappeler', 'pas_date' => '2027-11-25']] as $faux) {
            try {
                $this->creer($this->nina, $faux);
                self::fail('Accepté : ' . json_encode($faux));
            } catch (\InvalidArgumentException) {
            }
        }
        self::assertSame(0, BaseDeTest::compter('prospect'));
    }

    public function testUnDoublonSeSignaleEtLaPersonneTranche(): void
    {
        $roux = $this->creer($this->nina);
        // Même téléphone, autre écriture : avertissement, puis création si la personne confirme.
        try {
            $this->creer($this->leo, ['entreprise' => 'Roux Rénovation', 'telephone' => '+41 79 555 01 42', 'courriel' => '']);
            self::fail('Doublon non signalé.');
        } catch (DoublonProspect $d) {
            self::assertFalse($d->ferme);
            self::assertSame([['id' => $roux, 'motif' => 'telephone']], $d->fiches);
        }
        // Même nom sans la forme juridique, même numéro postal.
        try {
            $this->creer($this->leo, ['entreprise' => 'peinture roux', 'telephone' => '', 'courriel' => '']);
            self::fail('Doublon de nom non signalé.');
        } catch (DoublonProspect $d) {
            self::assertSame('nom', $d->fiches[0]['motif']);
        }
        $autre = $this->creer($this->leo, ['entreprise' => 'Roux Rénovation', 'telephone' => '+41 79 555 01 42', 'courriel' => ''], true, true);
        self::assertNotSame($roux, $autre);
        // Même numéro IDE : refus ferme, même confirmé.
        BaseDeTest::pdo()->exec("UPDATE prospect SET ide = 'CHE-123.456.789' WHERE id = $roux");
        try {
            $this->creer($this->leo, ['entreprise' => 'Tout autre nom', 'localite' => 'Bienne', 'telephone' => '', 'courriel' => '', 'ide' => 'CHE-123.456.789'], true, true);
            self::fail('Même IDE accepté.');
        } catch (DoublonProspect $d) {
            self::assertTrue($d->ferme);
        }
        self::assertSame(2, BaseDeTest::compter('prospect'));
    }

    public function testUnCompteDejaChezNousSeSignale(): void
    {
        $client = Monde::conteneur($this->horloge)->comptes()->creer('client@exemple.ch', 'Paysages du Doubs', false, null, 10);
        self::assertNotSame('', $client);
        try {
            $this->creer($this->nina, ['entreprise' => 'paysages du doubs', 'localite' => 'Saint-Ursanne', 'telephone' => '', 'courriel' => '']);
            self::fail('Compte existant non signalé.');
        } catch (DoublonProspect $d) {
            self::assertSame('Paysages du Doubs', $d->comptes[0]['entreprise']);
            self::assertSame([], $d->fiches);
        }
        // Les membres de l'équipe ne sont pas des artisans : leur nom ne ressemble à rien.
        self::assertGreaterThan(0, $this->creer($this->nina, ['entreprise' => 'Léo Favre', 'localite' => 'Alle', 'telephone' => '', 'courriel' => '']));
    }

    public function testUnCollegueVoitQuUneFicheEstSuivieSansEnLireLeContenu(): void
    {
        $id = $this->creer($this->nina);
        $this->prospects->noter($this->nina, $id, 'Quatre à cinq offres par mois, faites sur Word.');
        $leo = $this->vue($this->leo, $id);
        self::assertFalse($leo['entiere']);
        self::assertFalse($leo['ecrit']);
        self::assertSame('Nina Rey', $leo['fiche']['suiveur']);
        self::assertSame('Peinture Roux Sàrl', $leo['fiche']['entreprise']);
        // Le contrôle lit bien une fiche qui a un téléphone, un courriel et un contact.
        $entiere = $this->vue($this->nina, $id);
        self::assertTrue($entiere['entiere'] && $entiere['ecrit']);
        self::assertSame('079 555 01 42', $entiere['fiche']['telephone']);
        $sorti = json_encode($leo, JSON_UNESCAPED_UNICODE);
        foreach (['079', '0795550142', 'roux-peinture', 'Marc Roux', 'grossiste', 'Word'] as $secret) {
            self::assertStringNotContainsString($secret, (string) $sorti);
        }
        self::assertSame([], $this->prospects->notes($this->leo, false, $id));
        // L'administrateur et le modérateur lisent tout, sans écrire sur la fiche d'un autre.
        $chef = $this->vue($this->leo, $id, true);
        self::assertTrue($chef['entiere']);
        self::assertFalse($chef['ecrit']);
        self::assertCount(1, $this->prospects->notes($this->leo, true, $id));
    }

    public function testSeulCeluiQuiSuitEcrit(): void
    {
        $id = $this->creer($this->nina);
        foreach ([
            fn () => $this->prospects->noter($this->leo, $id, 'Je passe par là.'),
            fn () => $this->prospects->changerEtat($this->leo, $id, 'contacte'),
            fn () => $this->prospects->poserPas($this->leo, $id, 'Rappeler', '2026-11-26'),
            fn () => $this->prospects->lacher($this->leo, $id),
            fn () => $this->prospects->modifier($this->leo, $id, ['entreprise' => 'Volé'] + self::ROUX, true),
            fn () => $this->prospects->creerDemo($this->leo, Role::Commercial, $id),
            fn () => $this->prospects->prendre($this->leo, $id),
        ] as $n => $geste) {
            try {
                $geste();
                self::fail("Geste $n accepté.");
            } catch (\DomainException) {
            }
        }
        $fiche = BaseDeTest::ligne("SELECT entreprise, etat, suivi_par, pas_texte FROM prospect WHERE id = $id");
        self::assertSame(['Peinture Roux Sàrl', 'a_contacter', $this->nina, null], [$fiche['entreprise'], $fiche['etat'], (int) $fiche['suivi_par'], $fiche['pas_texte']]);
        self::assertSame(0, BaseDeTest::compter('prospect_note'));
    }

    public function testUneFicheResteSuivieTantQuOnNeLaLachePas(): void
    {
        $sansPas = $this->creer($this->nina);
        $avecPas = $this->creer($this->nina, ['entreprise' => 'Carrelage du Mont', 'localite' => '2900 Porrentruy', 'telephone' => '', 'courriel' => '', 'pas_texte' => 'Rendez-vous', 'pas_date' => '2026-12-04']);
        // Un pas se date au-delà de 30 jours : il ne sert plus à garder la fiche.
        $this->prospects->poserPas($this->nina, $avecPas, 'Rappeler après l\'hiver', '2027-03-15');
        // Quatre mois sans rien noter, pas manqué ou non : Nina suit toujours les deux fiches.
        $this->horloge->modify('2027-04-01 09:00:00');
        foreach ([$sansPas, $avecPas] as $id) {
            self::assertTrue($this->vue($this->nina, $id)['ecrit']);
            self::assertFalse($this->vue($this->leo, $id)['entiere']);
            self::assertArrayNotHasKey('echeance', $this->vue($this->leo, $id)['fiche']);
        }
        self::assertSame([], $this->prospects->liste($this->leo, false, 'libres', '')['fiches']);
        try {
            $this->prospects->prendre($this->leo, $sansPas);
            self::fail('Fiche suivie prise par un collègue.');
        } catch (\DomainException) {
        }
        // Nina la lâche : Léo la prend, les notes suivent la fiche (P6 = A).
        $this->prospects->lacher($this->nina, $sansPas);
        self::assertSame(1, $this->prospects->liste($this->leo, false, 'libres', '')['nombres']['libres']);
        $this->prospects->prendre($this->leo, $sansPas);
        self::assertSame('Léo Favre', $this->vue($this->nina, $sansPas)['fiche']['suiveur']);
        self::assertFalse($this->vue($this->nina, $sansPas)['entiere']);
        self::assertStringContainsString('pris', mb_strtolower($this->prospects->notes($this->leo, false, $sansPas)[0]['texte']));
    }

    public function testLacherEtPasInteresse(): void
    {
        $id = $this->creer($this->nina);
        $this->prospects->poserPas($this->nina, $id, 'Rappeler', '2026-11-26');
        $this->prospects->lacher($this->nina, $id);
        $fiche = $this->vue($this->leo, $id)['fiche'];
        self::assertSame([0, 'Nina Rey', null], [$fiche['tenue'], $fiche['avant'], $fiche['pas_texte']]);
        $this->prospects->prendre($this->leo, $id);
        $this->prospects->changerEtat($this->leo, $id, 'pas_interesse');
        // Classée : personne ne la tient, elle reste au catalogue, hors des « Libres ».
        self::assertSame(0, $this->vue($this->leo, $id)['fiche']['tenue']);
        self::assertSame([], $this->prospects->liste($this->nina, false, 'libres', '')['fiches']);
        self::assertCount(1, $this->prospects->liste($this->nina, false, 'tous', '')['fiches']);
        // Reprise : elle redevient « à contacter ».
        $this->prospects->prendre($this->nina, $id);
        self::assertSame('a_contacter', $this->vue($this->nina, $id)['fiche']['etat']);
    }

    public function testAucunPlafondDeFichesSuivies(): void
    {
        $pdo = BaseDeTest::pdo();
        for ($i = 1; $i <= 40; $i++) {
            $pdo->exec("INSERT INTO prospect (entreprise, nom_cle, localite, suivi_par, suivi_depuis, derniere_action_le, cree_par, cree_le) VALUES ('Fiche $i', 'fiche $i', 'Alle', {$this->nina}, '2026-11-24 08:00:00', '2026-11-24 08:00:00', {$this->nina}, '2026-11-24 08:00:00')");
        }
        $libre = $this->creer($this->leo, [], false);
        $this->prospects->prendre($this->nina, $libre);
        $this->creer($this->nina, ['entreprise' => 'Une de plus', 'localite' => 'Bure', 'telephone' => '', 'courriel' => '']);
        self::assertSame(42, $this->prospects->liste($this->nina, false, 'miens', '')['nombres']['miens']);
    }

    public function testLeProchainPasEtSonRetard(): void
    {
        $id = $this->creer($this->nina);
        $this->prospects->poserPas($this->nina, $id, 'Rappeler pour fixer la visite', '2026-11-25');
        $this->horloge->modify('2026-11-26 09:00:00');
        $fiche = $this->vue($this->nina, $id)['fiche'];
        self::assertTrue($fiche['retard']);
        self::assertSame(1, $this->prospects->liste($this->leo, true, 'retard', '')['nombres']['retard']);
        // « C'est fait » : le pas s'efface et laisse une note.
        $this->prospects->pasFait($this->nina, $id);
        self::assertNull($this->vue($this->nina, $id)['fiche']['pas_texte']);
        self::assertStringContainsString('Rappeler pour fixer la visite', $this->prospects->notes($this->nina, false, $id)[0]['texte']);
        // Le filtre « En retard » est réservé à ceux qui lisent tout.
        self::assertSame('miens', $this->prospects->liste($this->nina, false, 'retard', '')['vue']);
    }

    public function testCreerUneDemoLieLeCompteALaFiche(): void
    {
        $id = $this->creer($this->nina);
        $compte = $this->prospects->creerDemo($this->nina, Role::Commercial, $id);
        $demo = BaseDeTest::ligne("SELECT entreprise, demo, metier, courriel_saisi, cree_par, apporte_par FROM artisan WHERE id = $compte");
        self::assertSame(['Peinture Roux Sàrl', 1, 'peintre', 'marc@roux-peinture.example', $this->nina, $this->nina],
            [$demo['entreprise'], (int) $demo['demo'], $demo['metier'], $demo['courriel_saisi'], (int) $demo['cree_par'], (int) $demo['apporte_par']]);
        $fiche = $this->vue($this->nina, $id)['fiche'];
        self::assertSame([$compte, 'demo'], [$fiche['compte_id'], $fiche['compte']]);
        // Liée à un compte, la fiche reste suivie.
        $this->horloge->modify('2027-01-15 09:00:00');
        self::assertSame(1, $this->vue($this->nina, $id)['fiche']['tenue']);
        // Le compte devient un essai : la fiche le dit sans que personne ne la touche.
        BaseDeTest::pdo()->exec("UPDATE artisan SET demo = 0 WHERE id = $compte");
        self::assertSame('essai', $this->vue($this->leo, $id)['fiche']['compte']);
        $this->expectException(\DomainException::class);
        $this->prospects->creerDemo($this->nina, Role::Commercial, $id);
    }

    public function testFusionnerDeuxFiches(): void
    {
        $a = $this->creer($this->nina, ['telephone' => '', 'courriel' => '']);
        $b = $this->creer($this->leo, ['entreprise' => 'Roux Peinture', 'localite' => 'Courroux', 'ide' => 'CHE-123.456.789'], true, true);
        $this->prospects->noter($this->nina, $a, 'Note de Nina.');
        $this->prospects->noter($this->leo, $b, 'Note de Léo.');
        // Deux fiches suivies par deux personnes ne se fusionnent pas (ProspectsRevueTest) : Léo lâche la sienne.
        $this->prospects->lacher($this->leo, $b);
        $this->prospects->fusionner($this->leo, $a, $b);
        self::assertSame(1, BaseDeTest::compter('prospect'));
        $fiche = $this->vue($this->nina, $a)['fiche'];
        self::assertSame(['Peinture Roux Sàrl', '079 555 01 42', 'marc@roux-peinture.example', 'CHE-123.456.789', $this->nina],
            [$fiche['entreprise'], $fiche['telephone'], $fiche['courriel'], $fiche['ide'], $fiche['suivi_par']]);
        $textes = array_column($this->prospects->notes($this->nina, false, $a), 'texte');
        self::assertContains('Note de Nina.', $textes);
        self::assertContains('Note de Léo.', $textes);
        self::assertSame('prospect_fusionne', BaseDeTest::ligne('SELECT geste FROM journal_admin ORDER BY id DESC LIMIT 1')['geste']);
        $this->expectException(\DomainException::class);
        $this->prospects->fusionner($this->leo, $a, $a);
    }
}
