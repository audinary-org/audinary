<?php

namespace App\Database;

use PDO;
use PDOException;

class Connection
{
    private static ?PDO $pdo = null;

    public static function getPDO(): PDO
    {
        if (self::$pdo instanceof \PDO) {
            return self::$pdo;
        }

        // Load basic config directly to avoid circular dependency with loadConfig()
        $rootDir = realpath(__DIR__ . '/../../');
        $configFile = $rootDir . '/var/config/config.php';
        if (!file_exists($configFile)) {
            // Define the source config_sample.php location in the src folder
            $configSample = $rootDir . '/src/config_sample.php';
            if (file_exists($configSample)) {
                if (!copy($configSample, $configFile)) {
                    trigger_error("Failed to copy config_sample.php from src folder to config/config.php", E_USER_ERROR);
                }
            } else {
                throw new PDOException("Configuration file not found: " . $configFile);
            }
        }

        $config = include $configFile;

        try {
            $driver = getenv('DB_DRIVER') ?: ($config['dbDriver'] ?? 'pgsql');
            if ($driver !== 'pgsql') {
                throw new PDOException("Unsupported DB driver: {$driver}. Only pgsql is supported.");
            }

            $host = getenv('DB_HOST') ?: getenv('PGHOST') ?: ($config['dbHost'] ?? 'postgres');
            $port = getenv('DB_PORT') ?: getenv('PGPORT') ?: ($config['dbPort'] ?? 5432);
            $dbName = getenv('DB_NAME') ?: getenv('PGDATABASE') ?: ($config['dbName'] ?? 'audinary');
            $user = getenv('DB_USER') ?: getenv('PGUSER') ?: ($config['dbUser'] ?? 'audinary');
            $password = getenv('DB_PASSWORD') ?: getenv('PGPASSWORD') ?: ($config['dbPassword'] ?? '');

            $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port, $dbName);

            self::$pdo = new PDO(
                $dsn,
                $user,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );

            return self::$pdo;
        } catch (PDOException $e) {
            throw new PDOException("Database connection failed: " . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    public static function closeConnection(): void
    {
        self::$pdo = null;
    }
}
