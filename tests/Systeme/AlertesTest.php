<?php

declare(strict_types=1);

namespace OffreGen\Tests\Systeme;

use Monolog\Logger;
use OffreGen\Systeme\Alertes;
use OffreGen\Systeme\GestionnaireAlertes;
use OffreGen\Systeme\Messagerie;
use OffreGen\Systeme\MessagerieFichier;
use OffreGen\Tests\Outils\Dossiers;
use OffreGen\Tests\Outils\Monde;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Décision Q3 de Xavier (2026-11-18) : les erreurs de l'application partent par courriel à son adresse habituelle.
 * Une panne qui se répète chaque minute ne doit pas remplir sa boîte.
 */
final class AlertesTest extends TestCase
{
    private Dossiers $dossiers;
    private MockClock $horloge;

    protected function setUp(): void
    {
        $this->dossiers = new Dossiers();
        $this->horloge = new MockClock('2026-11-18 10:00:00', 'UTC');
    }

    protected function tearDown(): void
    {
        $this->dossiers->effacer();
    }

    private function alertes(?Messagerie $messagerie = null): Alertes
    {
        return new Alertes(
            $messagerie ?? new MessagerieFichier($this->dossiers->env()['COURRIELS_DOSSIER'], $this->horloge),
            'xavier@exemple.ch',
            $this->dossiers->racine . '/alertes',
            $this->horloge,
            'https://exemple.test',
        );
    }

    public function testLaMemeErreurPartUneFoisParHeure(): void
    {
        $a = $this->alertes();
        self::assertTrue($a->alerter('offreGen, traitement de 11111111-1111-4111-8111-111111111111 (echec) : délai dépassé après 30 s'));
        $this->horloge->modify('+59 minutes');
        // Autre enregistrement, autre durée : la même panne.
        self::assertFalse($a->alerter('offreGen, traitement de 22222222-2222-4222-8222-222222222222 (echec) : délai dépassé après 31 s'));
        self::assertCount(1, $this->dossiers->courriels());
        $this->horloge->modify('+2 minutes');
        self::assertTrue($a->alerter('offreGen, traitement de 33333333-3333-4333-8333-333333333333 (echec) : délai dépassé après 30 s'));
        self::assertCount(2, $this->dossiers->courriels());
    }

    public function testDeuxErreursDifferentesPartentChacune(): void
    {
        $a = $this->alertes();
        self::assertTrue($a->alerter('offreGen, erreur sur offre : base indisponible'));
        self::assertTrue($a->alerter('offreGen, purge de x : permission refusée'));
        self::assertCount(2, $this->dossiers->courriels());
    }

    public function testAuPlusTrenteCourrielsParJour(): void
    {
        $a = $this->alertes();
        for ($i = 0; $i < Alertes::MAX_PAR_JOUR + 5; $i++) {
            $a->alerter('offreGen, erreur numéro ' . str_repeat('x', $i));
        }
        self::assertCount(Alertes::MAX_PAR_JOUR, $this->dossiers->courriels());
        $this->horloge->modify('+1 day');
        self::assertTrue($a->alerter('offreGen, le lendemain'));
    }

    public function testLeCourrielDitQuoiEtOu(): void
    {
        $this->alertes()->alerter('offreGen, erreur sur offre : base indisponible');
        $texte = (string) file_get_contents($this->dossiers->courriels()[0]);
        self::assertStringContainsString("To: xavier@exemple.ch\r\n", $texte);
        self::assertStringContainsString('base indisponible', $texte);
        self::assertStringContainsString('https://exemple.test', $texte);
        self::assertStringContainsString('var/log/app.log', $texte);
    }

    public function testLeJournalAlerteSurUneErreurPasSurUnAvertissement(): void
    {
        $c = Monde::conteneur($this->horloge, $this->dossiers->env() + ['ALERTES_A' => 'xavier@exemple.ch', 'ALERTES_DOSSIER' => $this->dossiers->racine . '/alertes']);
        $c->journal()->warning('offreGen, transcription pas encore lisible');
        self::assertCount(0, $this->dossiers->courriels());
        $c->journal()->error('offreGen, erreur sur offre : boum');
        self::assertCount(1, $this->dossiers->courriels());
    }

    /**
     * Revue du 2026-11-18 (exploitation, défaut 2) : avec ALERTES_A rempli, une faute dans les réglages de messagerie
     * faisait tomber le journal, donc toutes les pages (500), /sante compris.
     */
    public function testUneMessagerieMalRegleeNeFaitTomberNiLeJournalNiLesPages(): void
    {
        foreach ([['MESSAGERIE' => 'smtp', 'MAILER_DSN' => ''], ['COURRIELS_REDIRIGER_VERS' => 'pas une adresse'], ['MESSAGERIE' => 'inconnue']] as $faute) {
            $c = Monde::conteneur($this->horloge, $this->dossiers->env() + $faute + ['ALERTES_A' => 'xavier@exemple.ch', 'ALERTES_DOSSIER' => $this->dossiers->racine . '/alertes']);
            $c->journal()->error('offreGen, erreur sur offre : boum');
            self::assertSame(0, $c->apresReponse()->nombre(), (string) json_encode($faute));
        }
    }

    /** Revue du 2026-11-18 (exploitation, défaut 3) : sans état écrit, ni silence ni plafond ; rien ne part. */
    public function testSansEtatEcritRienNePart(): void
    {
        $dossier = $this->dossiers->racine . '/alertes';
        mkdir($dossier, 0500);
        $a = new Alertes(new MessagerieFichier($this->dossiers->env()['COURRIELS_DOSSIER'], $this->horloge), 'xavier@exemple.ch', $dossier, $this->horloge, 'https://exemple.test');
        try {
            $a->alerter('offreGen, erreur sur offre : boum');
            self::fail('Une alerte sans état écrit devait être refusée.');
        } catch (\RuntimeException) {
            self::assertCount(0, $this->dossiers->courriels());
        } finally {
            chmod($dossier, 0700);
        }
    }

    /** Revue du 2026-11-18 (exploitation, défaut 9) : l'objet nomme la panne, jamais ce qu'un artisan a dicté. */
    public function testLObjetNeReprendPasLeDetailDuMessage(): void
    {
        $this->alertes()->alerter('offreGen, traitement de 11111111-1111-4111-8111-111111111111 (echec) : Mme Dupont, rue des Lilas 4');
        $texte = (string) file_get_contents($this->dossiers->courriels()[0]);
        preg_match('/Subject: =\?UTF-8\?B\?([^?]+)\?=/', $texte, $m);
        self::assertSame("Alerte offreGen\u{00A0}: traitement de <id> (echec)", base64_decode($m[1] ?? ''));
    }

    public function testSansAdresseAucuneAlerte(): void
    {
        Monde::conteneur($this->horloge, $this->dossiers->env())->journal()->error('offreGen, erreur sur offre : boum');
        self::assertCount(0, $this->dossiers->courriels());
    }

    /** Une messagerie en panne ne casse pas la page qui a noté l'erreur, et ne boucle pas sur elle-même. */
    public function testUneMessagerieEnPanneNeCassePasLeJournal(): void
    {
        $panne = new class implements Messagerie {
            public int $essais = 0;

            public function envoyer(string $destinataire, string $sujet, string $texte): void
            {
                $this->essais++;
                throw new \RuntimeException('serveur d\'envoi injoignable');
            }
        };
        $secours = [];
        $journal = new Logger('essai');
        $journal->pushHandler(new GestionnaireAlertes(fn (): Alertes => $this->alertes($panne), static function (string $m) use (&$secours): void {
            $secours[] = $m;
        }));
        $journal->error('offreGen, erreur sur offre : boum');
        self::assertSame(1, $panne->essais);
        self::assertSame(["offreGen, alerte non envoyée : serveur d'envoi injoignable"], $secours);
    }
}
