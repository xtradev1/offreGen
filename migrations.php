<?php

declare(strict_types=1);

// Configuration de vendor/bin/doctrine-migrations. La connexion est dans migrations-db.php.
return [
    'table_storage' => ['table_name' => 'doctrine_migration_versions'],
    'migrations_paths' => ['OffreGen\Migrations' => __DIR__ . '/migrations'],
    'all_or_nothing' => false,
    'transactional' => false,
    'check_database_platform' => true,
    'organize_migrations' => 'none',
];
