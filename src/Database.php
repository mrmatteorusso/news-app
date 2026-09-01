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
        self::ensureStageFourColumns($pdo);
        self::ensureStageFiveColumns($pdo);

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

    private static function ensureStageFourColumns(PDO $pdo): void
    {
        $columns = $pdo->query('PRAGMA table_info(article_evaluations)')->fetchAll();
        $columnNames = array_column($columns, 'name');
        if (!in_array('ranking_version', $columnNames, true)) {
            $pdo->exec("ALTER TABLE article_evaluations ADD COLUMN ranking_version TEXT NOT NULL DEFAULT 'unversioned'");
        }
    }

    private static function ensureStageFiveColumns(PDO $pdo): void
    {
        self::addColumns($pdo, 'article_evaluations', [
            'deterministic_explanation' => 'TEXT',
            'llm_selected' => 'INTEGER CHECK (llm_selected IN (0, 1))',
            'llm_relevance_score' => 'INTEGER CHECK (llm_relevance_score BETWEEN 0 AND 100)',
            'llm_requested_model' => 'TEXT',
            'llm_prompt_version' => 'TEXT',
            'llm_profile_hash' => 'TEXT',
            'llm_evaluated_at' => 'TEXT',
        ]);
        self::addColumns($pdo, 'llm_runs', [
            'resolved_model' => 'TEXT',
            'prompt_version' => "TEXT NOT NULL DEFAULT 'legacy'",
            'profile_hash' => "TEXT NOT NULL DEFAULT ''",
            'selected_count' => 'INTEGER NOT NULL DEFAULT 0',
            'chunk_count' => 'INTEGER NOT NULL DEFAULT 1',
        ]);

        $pdo->exec(
            'UPDATE article_evaluations
             SET deterministic_explanation = why_selected
             WHERE deterministic_explanation IS NULL'
        );
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_evaluations_llm_context
             ON article_evaluations(batch_id, section, llm_prompt_version, llm_profile_hash, llm_selected)'
        );
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_llm_runs_context
             ON llm_runs(batch_id, section, model, prompt_version, profile_hash, completed_at DESC)'
        );
    }

    private static function addColumns(PDO $pdo, string $table, array $definitions): void
    {
        $columns = $pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll();
        $columnNames = array_column($columns, 'name');
        foreach ($definitions as $column => $definition) {
            if (!in_array($column, $columnNames, true)) {
                $pdo->exec(sprintf('ALTER TABLE %s ADD COLUMN %s %s', $table, $column, $definition));
            }
        }
    }
}
