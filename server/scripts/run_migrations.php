<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/configHelper.php';

use App\Database\Migrations;

$migrations = new Migrations();

// Parse command line arguments
$checkMode = in_array('--check', $argv);

echo "=== Database Migration Runner ===\n\n";

// Ausstehende Migrationen anzeigen
$pendingMigrations = $migrations->getPendingMigrations();
if ($pendingMigrations === []) {
    echo "No pending migrations found.\n";
    if ($checkMode) {
        echo "No migrations needed.\n";
        exit(0); // Exit code 0: no migrations needed
    }

    // Show executed migrations when no pending migrations and not in check mode
    echo "\n=== Executed Migrations ===\n";
    foreach ($migrations->getExecutedMigrations() as $migration) {
        echo "✓ {$migration['migration_name']} ({$migration['executed_at']})\n";
    }
    echo "\nMigration process completed.\n";
} else {
    echo "Pending migrations:\n";
    foreach ($pendingMigrations as $migration) {
        echo "- $migration\n";
    }
    echo "\n";

    if ($checkMode) {
        echo "Migrations are needed.\n";
        exit(1); // Exit code 1: migrations needed
    }

    // Alle ausstehenden Migrationen ausführen
    echo "Running migrations...\n";
    if ($migrations->runAllMigrations()) {
        echo "\nAll migrations completed successfully.\n";

        // Show executed migrations after successful migration run
        echo "\n=== Executed Migrations ===\n";
        foreach ($migrations->getExecutedMigrations() as $migration) {
            echo "✓ {$migration['migration_name']} ({$migration['executed_at']})\n";
        }
        echo "\nMigration process completed.\n";
    } else {
        echo "\nSome migrations failed. Please check the output above.\n";
        exit(1);
    }
}
