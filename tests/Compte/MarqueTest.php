<?php

declare(strict_types=1);

namespace OffreGen\Tests\Compte;

use OffreGen\Compte\Marque;
use PHPUnit\Framework\TestCase;

final class MarqueTest extends TestCase
{
    public function testCouleurSaisie(): void
    {
        self::assertSame('#1D4E89', Marque::couleurValide('#1d4e89'));
        self::assertSame('#1D4E89', Marque::couleurValide(' #1D4E89 '));
        foreach (['', 'bleu', '#12345', '#GGGGGG', 'red', '#1d4e89;background:url(x)'] as $faux) {
            self::assertNull(Marque::couleurValide($faux), $faux);
        }
    }

    public function testUneCouleurClaireResteLisibleSurLeBlanc(): void
    {
        // Jaune pâle tiré d'un logo : illisible tel quel sur du papier blanc.
        $jaune = new Marque('sobre', '#F5E663');
        self::assertLessThan(4.5, Marque::contraste('#F5E663', '#FFFFFF'));
        self::assertGreaterThanOrEqual(4.5, Marque::contraste($jaune->principale(), '#FFFFFF'));
        // Une couleur déjà lisible ne change pas.
        self::assertSame('#1D4E89', (new Marque('sobre', '#1D4E89'))->principale());
    }

    /** 2b (Xavier, 2026-11-20) : une couleur qui tient 7:1 sur le blanc ne déclenche rien. */
    public function testUneCouleurQuiSeLitBienNeDemandeRien(): void
    {
        self::assertNull(Marque::propositions('#1D4E89'));
        self::assertNull(Marque::propositions('#3B3F46'));
    }

    /**
     * 2b : sous 4,5:1 (le blanc tapé par Xavier le 2026-11-20, un orange, un jaune), deux versions plus foncées de la même
     * teinte. « Visible » est la couleur que le document emploie déjà ; « Bien visible » tient 7:1.
     */
    public function testUneCouleurPaleRecoitDeuxVersionsPlusFonceesDeSaTeinte(): void
    {
        foreach (['#FFFFFF', '#F39C12', '#FFEB3B'] as $c) {
            $p = Marque::propositions($c);
            self::assertNotNull($p, $c);
            self::assertLessThan(4.5, $p['contraste'], $c);
            self::assertGreaterThanOrEqual(7.0, Marque::contraste($p['bien'], '#FFFFFF'), $c);
            self::assertGreaterThanOrEqual(4.5, Marque::contraste($p['visible'], '#FFFFFF'), $c);
            self::assertLessThan(7.0, Marque::contraste($p['visible'], '#FFFFFF'), $c);
            self::assertSame((new Marque('sobre', $c))->principale(), $p['visible'], "$c : Visible est la couleur du document");
        }
        $blanc = Marque::propositions('#FFFFFF');
        self::assertNotNull($blanc);
        self::assertMatchesRegularExpression('/^#([0-9A-F]{2})\1\1$/', $blanc['bien'], 'le blanc foncé reste un gris');
        self::assertSame('1', Marque::rapport($blanc['contraste']));
        // 2,16:1 : arrondi vers le bas, jamais plus beau qu'il n'est (la maquette écrivait 2,2).
        self::assertSame('2,1', Marque::rapport(Marque::contraste('#F39C12', '#FFFFFF')));
        self::assertSame('7,3', Marque::rapport(7.39));
    }

    /** 2b : entre 4,5 et 7:1, « Visible » est la couleur tapée elle-même. */
    public function testUneCouleurEntreLesDeuxSeuilsEstSaPropreVersionVisible(): void
    {
        $p = Marque::propositions('#0F7C8C');
        self::assertNotNull($p);
        self::assertSame('#0F7C8C', $p['visible']);
        self::assertGreaterThanOrEqual(7.0, Marque::contraste($p['bien'], '#FFFFFF'));
    }

    public function testTeinteEtDefauts(): void
    {
        self::assertSame('#E8EDF3', (new Marque('sobre', '#1D4E89'))->teinte(0.9));
        $defaut = new Marque();
        self::assertSame(['sobre', Marque::COULEUR_DEFAUT], [$defaut->modele, $defaut->couleur()]);
        self::assertSame('sobre', Marque::modeleValide('inconnu'));
        self::assertSame('moderne', Marque::modeleValide('moderne'));
        self::assertSame('lateral', Marque::modeleValide('lateral'));
        self::assertSame(['sobre', 'lettre', 'atelier', 'bandeau', 'moderne', 'lateral', 'enseigne', 'technique', 'recap', 'chantier', 'epure', 'marge'], array_keys(Marque::MODELES));
        foreach (array_keys(Marque::MODELES) as $cle) {
            self::assertLessThanOrEqual(12, strlen($cle), 'artisan.modele est un VARCHAR(12)');
            self::assertFileExists(dirname(__DIR__, 2) . "/templates/document/$cle.html.twig");
        }
    }

    /**
     * Un texte dans la couleur de l'artisan posé sur sa teinte (titres de poste de Chantier) : le fond teinté lui retire du
     * contraste. Mesuré le 2026-11-20 : #2E7D32 donne 5,13 sur le blanc et 4,49 sur sa teinte.
     */
    public function testLaCouleurResteLisibleSurSaTeinte(): void
    {
        foreach ([...Marque::TEINTES, '#F5E663', '#B8470F', '#A8650A', '#767676', '#FF0000'] as $couleur) {
            $m = new Marque('chantier', $couleur);
            self::assertGreaterThanOrEqual(4.5, Marque::contraste($m->surTeinte(), $m->teinte(0.9)), $couleur);
            self::assertStringContainsString('.doc .c-teinte.c-texte { color: ' . $m->surTeinte() . ' !important; }', $m->css());
        }
        self::assertLessThan(4.5, Marque::contraste('#2E7D32', (new Marque('chantier', '#2E7D32'))->teinte(0.9)), 'témoin : sans correction, le vert est sous le seuil');
        // Une couleur déjà assez sombre ne change pas.
        self::assertSame('#1D4E89', (new Marque('chantier', '#1D4E89'))->surTeinte());
    }

    /** L'adresse de la feuille de couleur suit son contenu : une règle de plus, et le navigateur recharge la feuille. */
    public function testLaVersionSuitLeContenuDeLaFeuille(): void
    {
        $vert = new Marque('sobre', '#2E7D32');
        self::assertSame(substr(hash('sha256', $vert->css() . 'sobre'), 0, 10), $vert->version());
        self::assertNotSame($vert->version(), (new Marque('sobre', '#1D4E89'))->version());
        self::assertSame($vert->version(), (new Marque('sobre', '#2E7D32'))->version());
    }

    /** Quatre familles dans l'écran de choix (Xavier, 2026-11-19) : chaque modèle est dans une famille, et une seule. */
    public function testChaqueModeleAUneFamilleEtUneSeule(): void
    {
        self::assertSame(['Classiques', 'Couleur', 'Détaillés', 'Simples'], array_column(Marque::FAMILLES, 'nom'));
        $ranges = array_merge(...array_column(Marque::FAMILLES, 'modeles'));
        self::assertSame(array_keys(Marque::MODELES), $ranges, "dans l'ordre de l'écran, sans doublon ni oubli");
    }

    /** Un modèle proposé selon le métier du compte (Xavier, 2026-11-19) ; un métier inconnu, « autre » ou absent : Sobre. */
    public function testPourMetier(): void
    {
        $attendus = ['peintre' => 'marge', 'platrier' => 'chantier', 'carreleur' => 'sobre', 'macon' => 'bandeau', 'sanitaire' => 'technique',
            'menuisier' => 'atelier', 'sols' => 'moderne', 'paysagiste' => 'epure', 'autre' => 'sobre'];
        self::assertSame(\OffreGen\Catalogue\CatalogueFictif::metiers(), array_keys($attendus), 'un modèle pour chaque métier du catalogue');
        foreach ($attendus as $metier => $modele) {
            self::assertSame($modele, Marque::pourMetier($metier), $metier);
        }
        self::assertSame('sobre', Marque::pourMetier(null));
        self::assertSame('sobre', Marque::pourMetier('inconnu'));
    }

    /** Enseigne (Xavier, 2026-11-20 : le nom officiel, « on regarde si ça joue ») : le corps du nom suit sa longueur. */
    public function testCorpsEnseigne(): void
    {
        foreach (['Volta' => 58, 'Carrelages Damier' => 58, 'Peinture Morel Sàrl' => 46, 'Maçonnerie du Vallon SA' => 46, 'Menuiserie des Tilleuls Sàrl' => 36,
                  'Sanitaire et Chauffage du Jura SA' => 28, 'Plâtrerie et Peinture des Trois Vallées SA' => 28] as $nom => $corps) {
            self::assertSame($corps, Marque::corpsEnseigne($nom), $nom);
        }
    }
}
