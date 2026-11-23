<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;

/**
 * L'aide à la vente (docs/plans/2026-11-21_onboarding/PLAN.md, lot 6). Les droits par rôle sont vérifiés pour toutes
 * les pages de l'administration par DroitsEquipeTest ; ici, le contenu.
 */
final class AideVenteTest extends TestCase
{
    public function testLeCommercialTrouveLaDemoLesObjectionsEtLeMessage(): void
    {
        $jeu = new Jeu();
        BaseDeTest::pdo()->exec("UPDATE artisan SET role = 'commercial' WHERE id = {$jeu->a}");
        $n = new Navigateur(Monde::conteneur());
        $n->connecter('a@exemple.ch');
        self::assertStringContainsString('href="/admin/aide-vente"', (string) $n->get('/admin/plus')->getContent());
        $page = (string) $n->get('/admin/aide-vente')->getContent();
        foreach (['La démo au rendez-vous', '7 jours et 10 offres', 'Les objections', 'C\'est combien', 'Le message de démo', '[le lien de la démo]'] as $attendu) {
            self::assertStringContainsString($attendu, str_replace("\u{00A0}", '&nbsp;', $page), $attendu);
        }
        // En attente de Xavier : O31, et le contenu des questions jamais montré.
        self::assertStringNotContainsString('Ce qu\'on ne dit pas', $page);
        self::assertStringNotContainsString('Les questions à poser', $page);
    }
}
