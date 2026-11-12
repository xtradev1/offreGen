<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Donnees\Statut;
use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Dossiers;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;

final class ImportTest extends TestCase
{
    private Jeu $jeu;
    private Dossiers $dossiers;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        $this->dossiers = new Dossiers();
    }

    protected function tearDown(): void
    {
        $this->dossiers->effacer();
    }

    private function navigateurConnecte(string $immediat = '0'): Navigateur
    {
        $n = new Navigateur(Monde::conteneur(env: $this->dossiers->env() + ['TRAITEMENT_IMMEDIAT' => $immediat]));
        self::assertSame(303, $n->connecter('a@exemple.ch')->getStatusCode());
        self::assertSame(200, $n->get('/import')->getStatusCode());
        return $n;
    }

    public function testAudioValideRangeHorsDePublicEtCreeUnEnregistrementRecu(): void
    {
        $n = $this->navigateurConnecte();
        $reponse = $n->post('/import', [], ['audio' => $this->dossiers->envoi('visite.m4a', 'Visite Dupont.m4a')]);

        self::assertSame(303, $reponse->getStatusCode());
        self::assertMatchesRegularExpression('#^/enregistrements/[0-9a-f-]{36}$#', (string) $reponse->headers->get('Location'));
        $uuid = substr((string) $reponse->headers->get('Location'), strlen('/enregistrements/'));
        $ligne = BaseDeTest::ligne("SELECT artisan_id, statut, type_mime, nom_origine, fichier FROM enregistrement WHERE uuid = '$uuid'");
        self::assertSame([$this->jeu->a, Statut::RECU, 'audio/x-m4a', 'Visite Dupont.m4a', "{$this->jeu->a}/$uuid.m4a"],
            [$ligne['artisan_id'], $ligne['statut'], $ligne['type_mime'], $ligne['nom_origine'], $ligne['fichier']]);
        $chemin = $this->dossiers->env()['AUDIO_DOSSIER'] . "/{$this->jeu->a}/$uuid.m4a";
        self::assertFileExists($chemin);
        self::assertSame('0600', substr(sprintf('%o', fileperms($chemin)), -4));
        // Sans traitement immédiat, rien n'a été programmé.
        self::assertSame(0, $n->c->apresReponse()->nombre());
    }

    public function testFauxAudioRefuse422SansRienGarder(): void
    {
        $n = $this->navigateurConnecte();
        $avant = BaseDeTest::compter('enregistrement');
        $reponse = $n->post('/import', [], ['audio' => $this->dossiers->envoi('texte.m4a')]);
        self::assertSame(422, $reponse->getStatusCode());
        self::assertStringContainsString("pas un enregistrement audio reconnu", (string) $reponse->getContent());
        self::assertSame($avant, BaseDeTest::compter('enregistrement'));
        self::assertSame([], glob($this->dossiers->env()['AUDIO_DOSSIER'] . '/*/*'));
    }

    public function testSansFichier422(): void
    {
        self::assertSame(422, $this->navigateurConnecte()->post('/import', [])->getStatusCode());
    }

    public function testCompteDemoAuPlafond429(): void
    {
        // Le jeu a déjà importé un enregistrement pour A aujourd'hui ; plafond à 1.
        BaseDeTest::pdo()->exec("UPDATE artisan SET demo = 1, plafond_jour = 1 WHERE id = {$this->jeu->a}");
        $n = $this->navigateurConnecte();
        $avant = BaseDeTest::compter('enregistrement');
        $reponse = $n->post('/import', [], ['audio' => $this->dossiers->envoi('visite.mp3')]);
        self::assertSame(429, $reponse->getStatusCode());
        self::assertSame($avant, BaseDeTest::compter('enregistrement'));
    }

    public function testCompteDemoSousLePlafondAccepte(): void
    {
        BaseDeTest::pdo()->exec("UPDATE artisan SET demo = 1, plafond_jour = 2 WHERE id = {$this->jeu->a}");
        $reponse = $this->navigateurConnecte()->post('/import', [], ['audio' => $this->dossiers->envoi('visite.mp3')]);
        self::assertSame(303, $reponse->getStatusCode());
    }

    public function testImportSansConnexionRedirige(): void
    {
        $n = new Navigateur(Monde::conteneur(env: $this->dossiers->env()));
        self::assertSame(303, $n->get('/import')->getStatusCode());
    }

    public function testTraitementImmediatProduitLOffreSansCron(): void
    {
        $n = $this->navigateurConnecte('1');
        $reponse = $n->post('/import', [], ['audio' => $this->dossiers->envoi('visite.ogg')]);
        $uuid = substr((string) $reponse->headers->get('Location'), strlen('/enregistrements/'));
        self::assertSame(1, $n->c->apresReponse()->nombre());

        // Ce que public/index.php fait une fois la réponse partie.
        $n->c->apresReponse()->executer();

        self::assertSame(1, BaseDeTest::compter('enregistrement', "uuid = '$uuid' AND statut = 'pret' AND supprimer_apres IS NOT NULL"));
        self::assertSame(1, BaseDeTest::compter('offre o JOIN enregistrement e ON e.id = o.enregistrement_id', "e.uuid = '$uuid'"));
        self::assertCount(1, $this->dossiers->courriels());
        self::assertStringContainsString('To: a@exemple.ch', (string) file_get_contents($this->dossiers->courriels()[0]));
    }
}
