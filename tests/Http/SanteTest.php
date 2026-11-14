<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Donnees\Base;
use OffreGen\Donnees\EtatBase;
use OffreGen\Http\Controleur\SanteControleur;
use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Monde;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET /sante (docs/DEPLOIEMENT.md) : {"commit":"<sha>","base":"ok","migrations":"ok"}, rien d'autre.
 * La sonde d'exposition le lit après chaque déploiement : bon commit, base joignable, migrations du code jouées.
 */
final class SanteTest extends TestCase
{
    private string $dossier;

    protected function setUp(): void
    {
        $this->dossier = sys_get_temp_dir() . '/offregen-sante-' . bin2hex(random_bytes(4));
        mkdir($this->dossier . '/migrations', 0700, true);
        foreach (glob(dirname(__DIR__, 2) . '/migrations/Version*.php') ?: [] as $f) {
            copy($f, $this->dossier . '/migrations/' . basename($f));
        }
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dossier . '/migrations/*') ?: []);
        rmdir($this->dossier . '/migrations');
        @unlink($this->dossier . '/REVISION');
        rmdir($this->dossier);
    }

    private function sante(?\Closure $base = null): Response
    {
        $base ??= static fn (): EtatBase => new EtatBase(new Base(BaseDeTest::pdo()));
        return (new SanteControleur($base, $this->dossier . '/REVISION', $this->dossier . '/migrations'))->etat();
    }

    /** @return array<string, string> */
    private static function json(Response $r): array
    {
        $d = json_decode((string) $r->getContent(), true);
        self::assertIsArray($d);
        /** @var array<string, string> $d */
        return $d;
    }

    public function testToutVaBien(): void
    {
        file_put_contents($this->dossier . '/REVISION', "4b24a43c0ffee\n");
        $r = $this->sante();
        self::assertSame(200, $r->getStatusCode());
        self::assertSame(['commit' => '4b24a43c0ffee', 'base' => 'ok', 'migrations' => 'ok'], self::json($r));
        self::assertStringContainsString('no-store', (string) $r->headers->get('Cache-Control'));
        self::assertStringContainsString('application/json', (string) $r->headers->get('Content-Type'));
    }

    public function testUneMigrationDuCodeNonJoueeRend503(): void
    {
        file_put_contents($this->dossier . '/migrations/Version29991231000001.php', '<?php');
        $r = $this->sante();
        self::assertSame(503, $r->getStatusCode());
        self::assertSame('en_retard', self::json($r)['migrations']);
        self::assertSame('ok', self::json($r)['base']);
    }

    /** Retour arrière : l'ancienne version tourne sur une base plus récente, elle reste saine. */
    public function testUneBasePlusRecenteQueLeCodeResteOk(): void
    {
        $fichiers = glob($this->dossier . '/migrations/Version*.php') ?: [];
        rsort($fichiers);
        unlink($fichiers[0]);
        self::assertSame('ok', self::json($this->sante())['migrations']);
    }

    public function testAucuneMigrationConnueNEstPasOk(): void
    {
        array_map('unlink', glob($this->dossier . '/migrations/*') ?: []);
        self::assertSame('ko', self::json($this->sante())['migrations']);
    }

    public function testBaseInjoignableRend503SansDetail(): void
    {
        $r = $this->sante(static fn (): EtatBase => throw new \PDOException('SQLSTATE[HY000] [2002] secret de connexion'));
        self::assertSame(503, $r->getStatusCode());
        self::assertSame(['commit' => 'inconnu', 'base' => 'ko', 'migrations' => 'ko'], self::json($r));
        self::assertStringNotContainsString('secret', (string) $r->getContent());
    }

    public function testSansFichierDeVersionLeCommitEstInconnu(): void
    {
        self::assertSame('inconnu', self::json($this->sante())['commit']);
    }

    public function testUnFichierDeVersionQuiNEstPasUnCommitNEstJamaisRecopie(): void
    {
        file_put_contents($this->dossier . '/REVISION', '<script>alert(1)</script>');
        $r = $this->sante();
        self::assertSame('inconnu', self::json($r)['commit']);
        self::assertStringNotContainsString('script', (string) $r->getContent());
    }

    public function testRoutePubliqueEnGetSeulement(): void
    {
        $noyau = Monde::conteneur()->noyau();
        $r = $noyau->traiter(Request::create('/sante'));
        self::assertSame(200, $r->getStatusCode());
        self::assertSame(['commit', 'base', 'migrations'], array_keys(self::json($r)));
        self::assertSame(405, $noyau->traiter(Request::create('/sante', 'POST'))->getStatusCode());
    }
}
