<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;

/**
 * Décisions D8 et D9 de Xavier (maquette 24, 2026-11-15).
 * D8 : « Envoyer » passe par le partage du téléphone, le PDF joint ; l'offre est notée envoyée quand le partage a
 * réussi (le navigateur l'annonce au serveur). Ensuite, l'action principale de l'offre devient « Facturer ».
 * D9 : facturer en un écran : la facture proposée, le pourcentage d'un acompte modifiable, « Émettre et envoyer »
 * (créée, émise, PDF prêt à partager) ou « Vérifier avant d'émettre » (brouillon, comme avant).
 */
final class EnvoyerFacturerTest extends TestCase
{
    private Jeu $jeu;
    private Navigateur $a;
    private string $offre;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        $this->a = new Navigateur(Monde::conteneur());
        $this->a->connecter('a@exemple.ch');
        $this->offre = '/offres/' . $this->jeu->offre['a'];
        // Une offre avec son client et un plan de trois acomptes, comme la démo.
        BaseDeTest::pdo()->exec("UPDATE offre SET client_id = {$this->jeu->client['a']} WHERE id = {$this->jeu->offre['a']}");
        $this->a->get('/reglages');
        $this->a->post('/reglages', ['paiement_type' => 'acomptes', 'paiement_acomptes_choix' => '30/30/40', 'retour' => '/reglages']);
    }

    private function page(string $chemin): string
    {
        $reponse = $this->a->get($chemin);
        self::assertSame(200, $reponse->getStatusCode(), $chemin);
        return (string) $reponse->getContent();
    }

    public function testEnvoyerPartageLePdfEtNoteLEnvoi(): void
    {
        $apercu = $this->page($this->offre . '/apercu');
        self::assertMatchesRegularExpression("#<a class=\"btn primary grand\" href=\"{$this->offre}/pdf\" download=\"Offre-\d+\.pdf\" data-partager data-titre=\"[^\"]+\">.*Envoyer</a>#s", $apercu);
        self::assertMatchesRegularExpression("#<form method=\"post\" action=\"{$this->offre}/envoyee\" data-apres-partage>#", $apercu);

        self::assertNull(BaseDeTest::ligne("SELECT envoyee_le FROM offre WHERE id = {$this->jeu->offre['a']}")['envoyee_le']);
        $r = $this->a->post($this->offre . '/envoyee', []);
        self::assertSame(303, $r->getStatusCode());
        self::assertNotNull(BaseDeTest::ligne("SELECT envoyee_le FROM offre WHERE id = {$this->jeu->offre['a']}")['envoyee_le']);
        $offre = $this->page($this->offre);
        self::assertMatchesRegularExpression('#<span class="status pret">Envoyée le \d{2}\.\d{2}\.\d{4}</span>#', $offre);
        self::assertMatchesRegularExpression("#<a class=\"btn primary grand\" href=\"{$this->offre}/facturer\">Facturer#", $offre);
    }

    public function testEmettreEtEnvoyerUnAcompteAuPourcentageChoisi(): void
    {
        $nouvelle = $this->page($this->offre . '/facturer');
        self::assertMatchesRegularExpression('#<input id="pourcent" name="pourcent" value="30"[^>]*inputmode="numeric"#', $nouvelle);
        self::assertMatchesRegularExpression('#<button class="btn primary grand" type="submit" form="facturer" name="suite" value="emettre">.*Émettre et envoyer</button>#s', $nouvelle);
        self::assertMatchesRegularExpression('#<button class="btn grand" type="submit" form="facturer" name="suite" value="verifier">Vérifier avant d\'émettre</button>#', $nouvelle);

        $r = $this->a->post($this->offre . '/facturer', ['genre' => 'acompte', 'pourcent' => '25', 'numero' => '2026-050', 'date' => '2026-11-07', 'echeance' => '2026-12-07', 'suite' => 'emettre']);
        self::assertSame(303, $r->getStatusCode());
        $f = BaseDeTest::ligne("SELECT * FROM facture WHERE numero = '2026-050'");
        self::assertSame(['emise', 25], [$f['statut'], (int) $f['acompte_pourcent']]);
        self::assertSame("/factures/{$f['id']}/apercu", $r->headers->get('Location'));
        self::assertStringContainsString("(25\u{00A0}%)", (string) BaseDeTest::ligne("SELECT designation FROM facture_ligne WHERE facture_id = {$f['id']}")['designation']);

        $apercu = $this->page("/factures/{$f['id']}/apercu");
        self::assertMatchesRegularExpression("#data-pdf=\"/factures/{$f['id']}/pdf\"#", $apercu);
        self::assertMatchesRegularExpression("#href=\"/factures/{$f['id']}/pdf\" download=\"Facture-2026-050\.pdf\" data-partager#", $apercu);
    }

    public function testVerifierAvantDEmettreGardeLeBrouillon(): void
    {
        $this->page($this->offre . '/facturer');
        $r = $this->a->post($this->offre . '/facturer', ['genre' => 'acompte', 'pourcent' => '30', 'numero' => '2026-051', 'date' => '2026-11-07', 'echeance' => '2026-12-07', 'suite' => 'verifier']);
        $f = BaseDeTest::ligne("SELECT * FROM facture WHERE numero = '2026-051'");
        self::assertSame('brouillon', $f['statut']);
        self::assertSame("/factures/{$f['id']}", $r->headers->get('Location'));
        // Un brouillon n'a pas de PDF : son aperçu renvoie à la facture.
        self::assertSame(303, $this->a->get("/factures/{$f['id']}/apercu")->getStatusCode());
    }

    public function testUnPourcentageHorsDesClousEstRefuse(): void
    {
        $this->page($this->offre . '/facturer');
        $r = $this->a->post($this->offre . '/facturer', ['genre' => 'acompte', 'pourcent' => '120', 'numero' => '2026-052', 'date' => '2026-11-07', 'echeance' => '2026-12-07', 'suite' => 'emettre']);
        self::assertSame(422, $r->getStatusCode());
        self::assertStringContainsString('Pourcentage entier, de 1 à 100', (string) $r->getContent());
        self::assertSame(0, BaseDeTest::compter('facture', "numero = '2026-052'"));
    }
}
