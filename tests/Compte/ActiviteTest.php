<?php

declare(strict_types=1);

namespace OffreGen\Tests\Compte;

use OffreGen\Compte\Activite;
use OffreGen\Compte\Conditions;
use OffreGen\Compte\PhrasesLettre;
use PHPUnit\Framework\TestCase;

/**
 * L'activité d'un compte (décision de Xavier du 2026-11-22) : Bâtiment, Extérieur, Prestations.
 */
final class ActiviteTest extends TestCase
{
    public function testLesTroisActivitesOntLesMemesMots(): void
    {
        $cles = array_keys(Activite::Batiment->mots());
        sort($cles);
        self::assertNotSame([], $cles);
        foreach (Activite::cases() as $a) {
            $siennes = array_keys($a->mots());
            sort($siennes);
            self::assertSame($cles, $siennes, $a->value);
            foreach ($a->mots() as $cle => $mot) {
                self::assertDoesNotMatchRegularExpression('/[\x{2014}\x{2013}]/u', $mot, "$a->value, $cle : pas de tiret long");
            }
        }
    }

    public function testLesMotsDuBatimentSontCeuxDAvant(): void
    {
        $m = Activite::Batiment->mots();
        self::assertSame('Parlez, pièce par pièce.', $m['dictee']);
        self::assertSame('Chantier', $m['bloc_titre']);
        self::assertSame('Début des travaux', $m['debut']);
        self::assertSame('Date de la visite', $m['date']);
    }

    public function testLesPrestationsNOntNiTitreDeBlocNiAcces(): void
    {
        $m = Activite::Prestations->mots();
        self::assertSame('', $m['bloc_titre']);
        self::assertSame('', $m['bloc_acces']);
        foreach ($m as $cle => $mot) {
            if ($cle === 'groupe') {
                continue;
            }
            self::assertDoesNotMatchRegularExpression('/chantier|travaux|visite/iu', $mot, $cle);
        }
    }

    public function testUneValeurInconnueVautLeBatiment(): void
    {
        self::assertSame(Activite::Batiment, Activite::lire(null));
        self::assertSame(Activite::Batiment, Activite::lire(''));
        self::assertSame(Activite::Batiment, Activite::lire('evenement'));
        self::assertSame(Activite::Exterieur, Activite::lire('exterieur'));
        self::assertSame(Activite::Prestations, Activite::lire('prestations'));
    }

    public function testLeMetierFixeLActivite(): void
    {
        self::assertSame(Activite::Exterieur, Activite::duMetier('paysagiste'));
        foreach (['informatique', 'conseil', 'marketing', 'prestation'] as $metier) {
            self::assertSame(Activite::Prestations, Activite::duMetier($metier), $metier);
        }
        foreach (['peintre', 'autre', 'inconnu', null] as $metier) {
            self::assertSame(Activite::Batiment, Activite::duMetier($metier), (string) $metier);
        }
    }

    public function testSeulesLesPrestationsNeRangentPasParGroupe(): void
    {
        self::assertTrue(Activite::Batiment->rangeParGroupe());
        self::assertTrue(Activite::Exterieur->rangeParGroupe());
        self::assertFalse(Activite::Prestations->rangeParGroupe());
    }

    public function testLesPhrasesFabriqueesSuiventLActivite(): void
    {
        $paiement = (new Conditions(30, 'jours', 30))->conditionsPaiement();
        $acomptes = Conditions::phraseAcomptes([30, 30, 40]);
        self::assertSame('À 30 jours dès la fin des travaux.', Activite::Batiment->dire($paiement));
        self::assertSame($paiement, Activite::Exterieur->dire($paiement));
        self::assertSame('À 30 jours dès la fin de la prestation.', Activite::Prestations->dire($paiement));
        self::assertSame("30\u{00A0}% à la commande, 30\u{00A0}% en cours de prestation, 40\u{00A0}% à la fin de la prestation.", Activite::Prestations->dire($acomptes));
        self::assertSame('Acompte au début de la prestation', Activite::Prestations->dire('Acompte au début des travaux'));
        self::assertSame('Acompte à mi-parcours', Activite::Prestations->dire('Acompte à mi-chantier'));
        // Tout ce que l'application fabrique pour le modèle Lettre et pour le paiement sort sans mot du bâtiment.
        $fabriquees = [$paiement, $acomptes, ...array_merge(...array_values(PhrasesLettre::TOURNURES))];
        foreach ($fabriquees as $phrase) {
            self::assertDoesNotMatchRegularExpression('/chantier|travaux|visite/iu', Activite::Prestations->dire($phrase), $phrase);
        }
    }

    public function testLesMetiersSeRangentSousLeurActivite(): void
    {
        $groupes = Activite::grouper(['peintre' => 'Peintre', 'paysagiste' => 'Jardinier, paysagiste', 'autre' => 'Autre métier', 'conseil' => 'Conseil']);
        self::assertSame([
            ['nom' => 'Bâtiment', 'metiers' => ['peintre' => 'Peintre', 'autre' => 'Autre métier']],
            ['nom' => 'Extérieur', 'metiers' => ['paysagiste' => 'Jardinier, paysagiste']],
            ['nom' => 'Prestations', 'metiers' => ['conseil' => 'Conseil']],
        ], $groupes);
        self::assertSame([['nom' => 'Bâtiment', 'metiers' => ['peintre' => 'Peintre']]], Activite::grouper(['peintre' => 'Peintre']), 'une activité sans métier ne paraît pas');
    }
}
