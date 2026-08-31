<?php

declare(strict_types=1);

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $storagePath = getenv('APP_STORAGE_PATH') ?: '/var/www/storage';
        if (!is_dir($storagePath) && !mkdir($storagePath, 0775, true) && !is_dir($storagePath)) {
            throw new RuntimeException('Unable to create the application storage directory.');
        }

        $databasePath = rtrim($storagePath, '/\\') . DIRECTORY_SEPARATOR . 'dashboard.sqlite';
        $pdo = new PDO('sqlite:' . $databasePath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 8,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA busy_timeout = 8000');

        $schemaPath = '/var/www/database/schema.sql';
        $schema = file_get_contents($schemaPath);
        if ($schema === false) {
            throw new RuntimeException('Unable to read the SQLite schema.');
        }
        $pdo->exec($schema);
        self::ensureStageTwoColumns($pdo);

        self::$connection = $pdo;
        return $pdo;
    }

    private static function ensureStageTwoColumns(PDO $pdo): void
    {
        $columns = $pdo->query('PRAGMA table_info(source_fetches)')->fetchAll();
        $columnNames = array_column($columns, 'name');
        if (!in_array('request_kind', $columnNames, true)) {
            $pdo->exec("ALTER TABLE source_fetches ADD COLUMN request_kind TEXT NOT NULL DEFAULT 'scan'");
        }
    }
}

