<?php

namespace App\Database;

use Exception;
use PDO;

class Migrations
{
    private string $migrationsDir;
    private PDO $pdo;

    public function __construct(?string $migrationsDir = null)
    {
        $this->migrationsDir = $migrationsDir ?? __DIR__ . '/../../migrations';
        $this->pdo = Connection::getPDO();
        $this->createMigrationsTable();
    }

    private function createMigrationsTable(): void
    {
        $sql = 'CREATE TABLE IF NOT EXISTS migrations ('
            . 'id BIGSERIAL PRIMARY KEY, '
            . 'migration_name VARCHAR(255) NOT NULL UNIQUE, '
            . 'executed_at TIMESTAMPTZ DEFAULT NOW())';

        $this->pdo->exec($sql);
    }

    public function runMigration(string $name, string $sql): bool
    {
        return $this->executeMigration($name, $sql, false);
    }

    public function runAllMigrations(): bool
    {
        if (!is_dir($this->migrationsDir)) {
            echo "Migrations directory does not exist: {$this->migrationsDir}\n";
            return false;
        }

        $migrationFiles = $this->getMigrationFiles();
        if ($migrationFiles === []) {
            echo "No migration files found.\n";
            return true;
        }

        foreach ($migrationFiles as $file) {
            $migrationName = pathinfo($file, PATHINFO_FILENAME);
            $sql = file_get_contents($file);
            if ($sql === false) {
                echo "Error: Could not read migration file: $file\n";
                return false;
            }

            if (!$this->executeMigration($migrationName, $sql, false)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Stille Version für App-Start
     */
    public function migrate(bool $silent = false): bool
    {
        if (!is_dir($this->migrationsDir)) {
            if (!$silent) {
                echo "Migrations directory does not exist: {$this->migrationsDir}\n";
            }
            return false;
        }

        $migrationFiles = $this->getMigrationFiles();
        if ($migrationFiles === []) {
            if (!$silent) {
                echo "No migration files found.\n";
            }
            return true;
        }

        foreach ($migrationFiles as $file) {
            $migrationName = pathinfo($file, PATHINFO_FILENAME);
            $sql = file_get_contents($file);
            if ($sql === false) {
                if (!$silent) {
                    echo "Error: Could not read migration file: $file\n";
                }
                return false;
            }

            if (!$this->executeMigration($migrationName, $sql, $silent)) {
                return false;
            }
        }

        return true;
    }

    private function executeMigration(string $name, string $sql, bool $silent): bool
    {
        if ($this->isMigrationExecuted($name)) {
            if (!$silent) {
                echo "Migration '$name' already executed.\n";
            }
            return true;
        }

        $sql = trim($this->transformSqlForDriver($sql));
        if ($sql === '') {
            if (!$silent) {
                echo "Migration '$name' has no executable statements.\n";
            }
            return false;
        }

        try {
            $this->pdo->exec($sql);
            $this->recordMigration($name);

            if (!$silent) {
                echo "Migration '$name' executed successfully.\n";
            }

            return true;
        } catch (Exception $e) {
            if (!$silent) {
                echo "Error executing migration '$name': " . $e->getMessage() . "\n";
            }
            return false;
        }
    }

    private function recordMigration(string $name): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO migrations (migration_name) VALUES (:name)');
        $stmt->execute([':name' => $name]);
    }

    private function isMigrationExecuted(string $name): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM migrations WHERE migration_name = :name LIMIT 1');
        $stmt->execute([':name' => $name]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Gibt alle Migrations-Dateien sortiert zurück
     *
     * @return array<int, string>
     */
    private function getMigrationFiles(): array
    {
        $files = glob($this->migrationsDir . '/*.sql');
        if ($files === [] || $files === false) {
            return [];
        }

        sort($files);
        return $files;
    }

    /**
     * Gibt alle ausgeführten Migrationen zurück
     *
     * @return array<int, array<string, string>>
     */
    public function getExecutedMigrations(): array
    {
        $stmt = $this->pdo->query('SELECT migration_name, executed_at FROM migrations ORDER BY executed_at');
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * Gibt ausstehende Migrationen zurück
     *
     * @return array<int, string>
     */
    public function getPendingMigrations(): array
    {
        $allFiles = $this->getMigrationFiles();
        $executedMigrations = array_column($this->getExecutedMigrations(), 'migration_name');

        $pending = [];
        foreach ($allFiles as $file) {
            $migrationName = pathinfo($file, PATHINFO_FILENAME);
            if (!in_array($migrationName, $executedMigrations, true)) {
                $pending[] = $migrationName;
            }
        }

        return $pending;
    }

    public function hasPendingMigrations(): bool
    {
        return $this->getPendingMigrations() !== [];
    }

    private function transformSqlForDriver(string $sql): string
    {
        // All migrations are authored for PostgreSQL; no transformation needed.
        return $sql;
    }
}
