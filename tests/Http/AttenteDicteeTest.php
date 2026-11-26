<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Http\Controleur\EnregistrementControleur;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;

/**
 * L'attente après « Terminer » (maquette v2 du parcours de la dictée, validée par Xavier le 2026-11-26) : un anneau qui
 * se remplit, trois étapes nommées, la phrase qui permet de quitter. « Offre prête » n'est plus une étape : prête,
 * l'offre s'ouvre.
 */
final class AttenteDicteeTest extends TestCase
{
    private Navigateur $a;

    protected function setUp(): void
    {
        new Jeu();
        $this->a = new Navigateur(Monde::conteneur());
        $this->a->connecter('a@exemple.ch');
    }

    public function testLaFenetreDeDicteePorteLAnneauEtTroisEtapes(): void
    {
        $reponse = $this->a->get('/');
        self::assertSame(200, $reponse->getStatusCode());
        $page = (string) $reponse->getContent();
        self::assertSame(1, preg_match('#<div class="attente" data-etat="envoi" hidden>(.*?)</div>\s*<div class="prete"#s', $page, $m), 'le bloc de l\'attente');
        $attente = $m[1] ?? '';
        self::assertStringContainsString("<div class=\"anneau\" data-anneau role=\"progressbar\" aria-valuemin=\"0\" aria-valuemax=\"100\" aria-valuenow=\"0\" aria-label=\"Préparation de l'offre\">", $attente);
        self::assertStringContainsString('data-anneau-part', $attente);
        self::assertStringContainsString('data-anneau-reste', $attente);
        self::assertSame(3, substr_count($attente, '<li class='), 'trois étapes');
        self::assertMatchesRegularExpression('#<li class="cur">.*Envoi de l&\#039;enregistrement.*<li class="todo">.*Transcription de la dictée.*<li class="todo">.*Lignes de l&\#039;offre#s', $attente);
        self::assertStringNotContainsString('Offre prête', $attente);
        self::assertStringContainsString('data-attente-quitter', $attente);
        self::assertStringContainsString('Vous pouvez travailler sur autre chose ou quitter l\'application&nbsp;: l\'offre vous attendra dans «&nbsp;Offres&nbsp;».', $attente);
        // Xavier, 2026-11-26 : « Il faut pouvoir fermer la préparation de l'offre. Un bouton avec écrit mettre en
        // arrière-plan pour travailler sur autre chose ». Caché pendant l'envoi : app.js le montre une fois l'audio au serveur.
        self::assertStringContainsString('<button class="btn large" type="button" data-arriere-plan data-echec="Fermer" hidden>Mettre en arrière-plan</button>', $attente);
    }

    public function testPreteToutesLesEtapesSontFaites(): void
    {
        self::assertCount(3, EnregistrementControleur::ETAPES);
        self::assertSame(['done', 'done', 'done'], array_column(EnregistrementControleur::etapes(3, false), 'etat'));
        self::assertSame(['done', 'bad', 'todo'], array_column(EnregistrementControleur::etapes(1, true), 'etat'));
    }
}
