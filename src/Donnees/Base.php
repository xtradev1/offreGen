<?php

declare(strict_types=1);

namespace OffreGen\Donnees;

/**
 * Seul point d'entrée vers la base : requêtes préparées, rien d'autre.
 * Les paramètres sont toujours liés, jamais collés dans le SQL.
 */
final class Base
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    /**
     * @param array<string, scalar|null> $p
     * @return list<array<string, mixed>>
     */
    public function lire(string $sql, array $p = []): array
    {
        $requete = $this->executer($sql, $p);
        /** @var list<array<string, mixed>> $lignes */
        $lignes = $requete->fetchAll(\PDO::FETCH_ASSOC);
        return $lignes;
    }

    /**
     * @param array<string, scalar|null> $p
     * @return array<string, mixed>|null
     */
    public function un(string $sql, array $p = []): ?array
    {
        $ligne = $this->executer($sql, $p)->fetch(\PDO::FETCH_ASSOC);
        /** @var array<string, mixed>|false $ligne */
        return $ligne === false ? null : $ligne;
    }

    /** @param array<string, scalar|null> $p */
    public function valeur(string $sql, array $p = []): mixed
    {
        return $this->executer($sql, $p)->fetchColumn();
    }

    /**
     * @param array<string, scalar|null> $p
     * @return int nombre de lignes touchées
     */
    public function ecrire(string $sql, array $p = []): int
    {
        return $this->executer($sql, $p)->rowCount();
    }

    public function dernierId(): int
    {
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Exécute $f dans une transaction ; une exception annule tout et remonte.
     * Une transaction déjà ouverte est réutilisée.
     *
     * @template T
     * @param callable(): T $f
     * @return T
     */
    public function transaction(callable $f): mixed
    {
        if ($this->pdo->inTransaction()) {
            return $f();
        }
        $this->pdo->beginTransaction();
        try {
            $resultat = $f();
            $this->pdo->commit();
            return $resultat;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /** @param array<string, scalar|null> $p */
    private function executer(string $sql, array $p): \PDOStatement
    {
        $requete = $this->pdo->prepare($sql);
        foreach ($p as $nom => $valeur) {
            $type = match (true) {
                is_int($valeur) => \PDO::PARAM_INT,
                is_bool($valeur) => \PDO::PARAM_BOOL,
                $valeur === null => \PDO::PARAM_NULL,
                default => \PDO::PARAM_STR,
            };
            $requete->bindValue(':' . $nom, $valeur, $type);
        }
        $requete->execute();
        return $requete;
    }
}
