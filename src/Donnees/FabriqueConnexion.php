<?php

declare(strict_types=1);

namespace OffreGen\Donnees;

/**
 * Lit les paramètres de la base dans l'environnement (.env) : un utilisateur d'application,
 * qui lit et écrit, et un utilisateur de structure, réservé aux migrations.
 */
final class FabriqueConnexion
{
    /** @param array<string, string> $env */
    public function __construct(private readonly array $env)
    {
    }

    public function pdo(): \PDO
    {
        $pdo = new \PDO($this->dsn(), $this->valeur('DB_UTILISATEUR'), $this->valeur('DB_MOT_DE_PASSE'), [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        // Toutes les dates sont calculées en PHP, en UTC ; la base ne doit pas les décaler.
        $pdo->exec("SET time_zone = '+00:00'");
        return $pdo;
    }

    /**
     * Paramètres DBAL pour doctrine/migrations, avec l'utilisateur de structure.
     *
     * @return array{driver: 'pdo_mysql', dbname: string, user: string, password: string, charset: string, unix_socket?: string, host?: string, port?: int}
     */
    public function parametresMigrations(): array
    {
        $p = [
            'driver' => 'pdo_mysql',
            'dbname' => $this->valeur('DB_NOM'),
            'user' => $this->valeur('DB_STRUCTURE_UTILISATEUR'),
            'password' => $this->valeur('DB_STRUCTURE_MOT_DE_PASSE'),
            'charset' => 'utf8mb4',
        ];
        if (($this->env['DB_SOCKET'] ?? '') !== '') {
            $p['unix_socket'] = $this->env['DB_SOCKET'];
        } else {
            $p['host'] = $this->valeur('DB_HOTE');
            $p['port'] = (int) ($this->env['DB_PORT'] ?? 3306);
        }
        return $p;
    }

    public function nomBase(): string
    {
        return $this->valeur('DB_NOM');
    }

    private function dsn(): string
    {
        $base = 'dbname=' . $this->valeur('DB_NOM') . ';charset=utf8mb4';
        if (($this->env['DB_SOCKET'] ?? '') !== '') {
            return 'mysql:unix_socket=' . $this->env['DB_SOCKET'] . ';' . $base;
        }
        return 'mysql:host=' . $this->valeur('DB_HOTE') . ';port=' . (int) ($this->env['DB_PORT'] ?? 3306) . ';' . $base;
    }

    private function valeur(string $cle): string
    {
        $v = $this->env[$cle] ?? '';
        if ($v === '') {
            throw new \RuntimeException("Paramètre de base manquant dans .env : $cle");
        }
        return $v;
    }
}
