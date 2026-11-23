<?php

declare(strict_types=1);

namespace OffreGen\Tests\Compte;

use OffreGen\Compte\PhrasesLettre;
use PHPUnit\Framework\TestCase;

/**
 * Les phrases du modèle Lettre, réglables par l'artisan (maquette MAQUETTE-lettre-phrases.html, réponses de Xavier du
 * 2026-11-20 : L1 à L6).
 */
final class PhrasesLettreTest extends TestCase
{
    public function testSansReglageLesPhrasesSontCellesDAujourdHui(): void
    {
        $p = new PhrasesLettre();
        self::assertSame("Madame, Monsieur,", $p->appel('Madame Exemple'));
        self::assertSame('Nous vous remettons notre offre pour les travaux suivants.', $p->texte('offre_debut', 'Famille Exemple'));
        self::assertSame('Nous restons à votre disposition pour toute question et vous adressons, Madame, Monsieur, nos salutations les meilleures.',
            $p->texte('offre_fin', 'Famille Exemple'));
        self::assertSame('Nous vous adressons notre facture pour les travaux suivants.', $p->texte('facture_debut', ''));
        self::assertSame('Nous vous remercions de votre confiance et vous adressons, Madame, Monsieur, nos salutations les meilleures.', $p->texte('facture_fin', ''));
    }

    /** L6 : trois tournures par phrase, la première est celle d'aujourd'hui. */
    public function testChaquePhraseATroisTournuresDontLActuelle(): void
    {
        foreach (PhrasesLettre::ACTUELLES as $cle => $actuelle) {
            self::assertCount(3, PhrasesLettre::TOURNURES[$cle], $cle);
            self::assertSame($actuelle, PhrasesLettre::TOURNURES[$cle][0], $cle);
        }
    }

    /** L3 : selon le client, « Madame, » ou « Monsieur, », sinon « Madame, Monsieur, » ; la fin suit. */
    public function testLaFormuleDAppelSelonLeClientEtLaFinQuiSuit(): void
    {
        $p = new PhrasesLettre(appelSelonClient: true);
        self::assertSame('Madame,', $p->appel('Madame Exemple'));
        self::assertSame('Madame,', $p->appel('Mme Exemple'));
        self::assertSame('Monsieur,', $p->appel('Monsieur Modèle'));
        self::assertSame('Monsieur,', $p->appel('M. Modèle'));
        self::assertSame('Madame, Monsieur,', $p->appel('Famille Exemple'));
        self::assertSame('Madame, Monsieur,', $p->appel('Menuiserie Exemple SA'));
        self::assertSame('Madame, Monsieur,', $p->appel(null), 'une offre dont le client reste à choisir');
        self::assertSame('Madame, Monsieur,', $p->appel('Madameexemple Sàrl'), 'un mot qui commence par Madame');
        self::assertSame('Nous restons à votre disposition pour toute question et vous adressons, Madame, nos salutations les meilleures.',
            $p->texte('offre_fin', 'Madame Exemple'));
        self::assertSame('Nous restons à votre disposition pour toute question et vous adressons, Madame, Monsieur, nos salutations les meilleures.',
            $p->texte('offre_fin', 'Famille Exemple'));
        self::assertSame('Madame, Monsieur,', (new PhrasesLettre())->appel('Monsieur Modèle'), 'la même pour tous');
    }

    public function testLaSaisieGardeLesPhrasesEtSesEspacesInsecables(): void
    {
        [$p, $erreurs] = PhrasesLettre::depuisSaisie(['lettre_appel' => 'client', 'lettre_offre_debut' => "  Comme convenu,\n voici notre offre : la voici !  ",
            'lettre_offre_fin' => '', 'lettre_facture_debut' => PhrasesLettre::ACTUELLES['facture_debut'], 'lettre_facture_fin' => 'Merci.']);
        self::assertSame([], $erreurs);
        self::assertNotNull($p);
        self::assertTrue($p->appelSelonClient);
        self::assertSame('Comme convenu, voici notre offre : la voici !', $p->offreDebut);
        self::assertSame("Comme convenu, voici notre offre\u{00A0}: la voici\u{00A0}!", $p->texte('offre_debut', ''));
        self::assertSame('', $p->offreFin, 'vide : la phrase actuelle');
        self::assertSame(PhrasesLettre::ACTUELLES['offre_fin'], $p->texte('offre_fin', ''));
        self::assertSame('', $p->factureDebut, "la phrase actuelle n'est pas recopiée : elle suivra si elle change");
        self::assertSame('Merci.', $p->texte('facture_fin', ''));
        self::assertSame(['lettre_appel' => 'client', 'lettre_offre_debut' => 'Comme convenu, voici notre offre : la voici !',
            'lettre_offre_fin' => PhrasesLettre::ACTUELLES['offre_fin'], 'lettre_facture_debut' => PhrasesLettre::ACTUELLES['facture_debut'],
            'lettre_facture_fin' => 'Merci.', 'lettre_partout' => '0'], array_slice($p->versChamps(), 0, 6));
    }

    public function testUneSaisieTropLongueOuIllisibleEstRefusee(): void
    {
        [$p, $erreurs] = PhrasesLettre::depuisSaisie(['lettre_offre_debut' => str_repeat('a', 501), 'lettre_offre_fin' => "Merci \xC3\x28"]);
        self::assertNull($p);
        self::assertSame(['lettre_offre_debut' => '500 caractères au plus.', 'lettre_offre_fin' => 'Ce texte contient des caractères illisibles.'], $erreurs);
        [$p] = PhrasesLettre::depuisSaisie(['lettre_offre_debut' => str_repeat('é', 500), 'lettre_appel' => 'autre chose']);
        self::assertNotNull($p, '500 caractères accentués passent');
        self::assertFalse($p->appelSelonClient, 'une valeur inconnue vaut « la même pour tous »');
    }
}
