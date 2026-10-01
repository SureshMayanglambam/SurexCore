<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;

/**
 * SQL dump of this site's tables, written in PHP (no mysqldump needed — shared hosting often has none).
 * Import the file with phpMyAdmin to restore.
 */
class DatabaseExport
{
    /** Rows per INSERT statement. */
    private const BATCH = 100;

    public function __construct(private readonly BaseConnection $db)
    {
    }

    /**
     * Write the dump to $file. Importing it replaces every table with this data, except the tables in
     * $keepExisting (without prefix, e.g. activity_log): those are never dropped or filled — the importing
     * database keeps its own rows, and only gets an empty table if it has none.
     */
    public function toFile(string $file, array $keepExisting = []): void
    {
        $out    = fopen($file, 'wb');
        $prefix = $this->db->getPrefix();
        $tables = $this->orderedTables();

        fwrite($out, "-- " . setting('site_name', 'SurexCore') . " database backup\n-- " . date('Y-m-d H:i:s') . "\n\n"
            . "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");

        // Drop the existing tables first, tables that reference others before the tables they reference
        // (repeater rows → entries → users). Then the import also works when foreign key checks stay on.
        foreach (array_reverse($tables) as $table) {
            if (! in_array(substr($table, strlen($prefix)), $keepExisting, true)) {
                fwrite($out, 'DROP TABLE IF EXISTS ' . $this->db->escapeIdentifiers($table) . ";\n");
            }
        }
        fwrite($out, "\n");

        foreach ($tables as $table) {
            $create = $this->db->query('SHOW CREATE TABLE ' . $this->db->escapeIdentifiers($table))->getRowArray();
            // Many shared hosts run MariaDB, which doesn't know MySQL 8's default collation.
            $create = str_replace('utf8mb4_0900_ai_ci', 'utf8mb4_unicode_ci', (string) end($create));

            if (in_array(substr($table, strlen($prefix)), $keepExisting, true)) {
                fwrite($out, preg_replace('/^CREATE TABLE/', 'CREATE TABLE IF NOT EXISTS', $create) . ";\n\n");

                continue;
            }

            fwrite($out, "{$create};\n\n");
            $this->writeRows($out, $table);
        }

        fwrite($out, "SET FOREIGN_KEY_CHECKS = 1;\n");
        fclose($out);
    }

    /**
     * This site's tables (with prefix), referenced tables before the tables that reference them:
     * users → entries → repeater rows.
     *
     * @return list<string>
     */
    private function orderedTables(): array
    {
        $prefix = $this->db->getPrefix();
        $tables = array_values(array_filter($this->db->listTables(), static fn ($t) => $prefix === '' || str_starts_with($t, $prefix)));

        $parents = [];
        $rows    = $this->db->query(
            'SELECT TABLE_NAME AS child, REFERENCED_TABLE_NAME AS parent FROM information_schema.KEY_COLUMN_USAGE'
            . ' WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL'
        )->getResultArray();
        foreach ($rows as $row) {
            if ($row['child'] !== $row['parent']) {
                $parents[$row['child']][] = $row['parent'];
            }
        }

        $ordered = [];
        $visit   = function (string $table, array $path = []) use (&$visit, &$ordered, $parents, $tables): void {
            if (in_array($table, $ordered, true) || in_array($table, $path, true) || ! in_array($table, $tables, true)) {
                return;
            }
            foreach ($parents[$table] ?? [] as $parent) {
                $visit($parent, [...$path, $table]);
            }
            $ordered[] = $table;
        };
        foreach ($tables as $table) {
            $visit($table);
        }

        return $ordered;
    }

    /**
     * INSERT statements in batches of rows.
     */
    private function writeRows($out, string $table): void
    {
        $query   = $this->db->query('SELECT * FROM ' . $this->db->escapeIdentifiers($table));
        $columns = null;
        $batch   = [];

        while ($row = $query->getUnbufferedRow('array')) {
            $columns ??= array_keys($row);
            $batch[]   = '(' . implode(',', array_map(fn ($v) => $v === null ? 'NULL' : $this->db->escape((string) $v), $row)) . ')';

            if (count($batch) === self::BATCH) {
                $this->writeInsert($out, $table, $columns, $batch);
                $batch = [];
            }
        }
        if ($batch !== []) {
            $this->writeInsert($out, $table, $columns, $batch);
        }
        $query->freeResult();
        fwrite($out, "\n");
    }

    private function writeInsert($out, string $table, array $columns, array $values): void
    {
        $cols = implode(',', array_map(fn ($c) => $this->db->escapeIdentifiers($c), $columns));
        fwrite($out, 'INSERT INTO ' . $this->db->escapeIdentifiers($table) . " ({$cols}) VALUES\n" . implode(",\n", $values) . ";\n");
    }
}
