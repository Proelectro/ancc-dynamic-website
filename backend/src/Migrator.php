<?php

declare(strict_types=1);

namespace Ancc;

use RuntimeException;

/**
 * Applies migrations/*.sql in file-name order and records them in `schema_migrations`.
 * Name new files with a timestamp, e.g. 20261126_1430_events_create.sql, so two branches never pick
 * the same name (001_… is the original core schema). Migrations only move forward: to change the
 * schema, add a new file; never edit one that has been merged.
 */
final class Migrator
{
    public function __construct(private Db $db, private string $dir)
    {
    }

    /** @return list<string> names of files applied in this run */
    public function migrate(?callable $log = null): array
    {
        $log ??= static fn (string $m) => null;
        $this->guardAgainstOldSchema();
        $this->db->pdo()->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
            name VARCHAR(255) NOT NULL PRIMARY KEY,
            applied_at DATETIME(6) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        $done = array_flip($this->db->run('SELECT name FROM schema_migrations')->fetchAll(\PDO::FETCH_COLUMN));
        $files = glob($this->dir . '/*.sql') ?: [];
        sort($files);
        $applied = [];
        foreach ($files as $file) {
            $name = basename($file);
            if (isset($done[$name])) {
                continue;
            }
            $log("applying $name");
            // MySQL commits DDL implicitly, so each statement runs on its own.
            foreach (self::statements((string) file_get_contents($file)) as $sql) {
                $this->db->pdo()->exec($sql);
            }
            $this->db->run('INSERT INTO schema_migrations (name, applied_at) VALUES (?, ?)', [$name, Db::now()]);
            $applied[] = $name;
        }
        if (!$applied) {
            $log('database is up to date');
        }
        return $applied;
    }

    /** Drops every table in the database. Only for tests and resetting a preview. */
    public function dropAll(): void
    {
        $tables = $this->db->run('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN);
        $this->db->pdo()->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($tables as $t) {
            $this->db->pdo()->exec('DROP TABLE `' . str_replace('`', '', (string) $t) . '`');
        }
        $this->db->pdo()->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    /** The first (Python) version used Alembic. Refuse to mix the two schemas silently. */
    private function guardAgainstOldSchema(): void
    {
        $old = $this->db->scalar("SELECT COUNT(*) FROM information_schema.tables
            WHERE table_schema = DATABASE() AND table_name = 'alembic_version'");
        if ((int) $old > 0) {
            throw new RuntimeException(
                'Found tables from the old Python version (alembic_version). They only hold preview data. '
                . 'Reset the database to start fresh (deploy/iitd/deploy.sh reset-db, or php bin/migrate.php --reset).'
            );
        }
    }

    /** @return list<string> */
    private static function statements(string $sql): array
    {
        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql; // strip comment lines
        $sql = preg_replace('/\s--\s.*$/m', '', $sql) ?? $sql;  // strip trailing comments
        return array_values(array_filter(array_map('trim', explode(';', $sql)), static fn ($s) => $s !== ''));
    }
}
