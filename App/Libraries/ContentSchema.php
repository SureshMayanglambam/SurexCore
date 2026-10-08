<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Forge;
use Config\Database;

/**
 * Real database tables for content types (like Strapi / Directus).
 *
 *   content type "store"          → table  store      (standard columns + one column per field)
 *   field in a group "company"    → column company_address
 *   repeater "faq"                → table  store__faq (id, parent_id, sort, sub field columns)
 *   repeater inside a repeater    → table  store__faq__links, parent_id → store__faq.id
 *
 * The schema is always derived from the field definitions. sync() compares a definition with
 * the previous one (renames are tracked by field key) and with the actual database, and runs
 * only the ALTER/CREATE/DROP statements needed.
 */
class ContentSchema
{
    /** Columns every content type table has. Field names may not use these. */
    public const BASE_COLUMNS = [
        'id', 'title', 'slug', 'status', 'author_id', 'meta_title', 'meta_description',
        'published_at', 'published_until', 'created_at', 'updated_at', 'deleted_at',
    ];

    /** Columns every repeater table has. */
    public const ROW_COLUMNS = ['id', 'parent_id', 'sort'];

    /** Tables the CMS itself uses; content types can't take these names. */
    public const SYSTEM_TABLES = ['users', 'settings', 'content_types', 'activity_log', 'migrations', 'entries'];

    /** How each field type is stored. */
    private const STORAGE = [
        'text'     => 'varchar',
        'email'    => 'varchar',
        'select'   => 'varchar',
        'radio'    => 'varchar',
        'image'    => 'varchar',
        'file'     => 'varchar',
        'textarea' => 'text',
        'url'      => 'text',
        'editor'   => 'longtext',
        'number'   => 'double',
        'date'     => 'date',
        'toggle'   => 'bool',
        'checkbox' => 'json',
        'relation' => 'json',
    ];

    /** Storage that can be widened in place without losing data. */
    private const WIDENING = ['varchar' => 1, 'text' => 2, 'longtext' => 3];

    /** Placeholder for the site URL inside stored HTML, so content survives a domain change. */
    public const BASE_URL_TOKEN = '{{base_url}}';

    private BaseConnection $db;
    private Forge $forge;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db    = $db ?? Database::connect();
        $this->forge = Database::forge($this->db);
        helper('url');
    }

    // ==================================================================
    // Layout: definition → tables and columns
    // ==================================================================

    public static function tableName(string $slug): string
    {
        return str_replace('-', '_', $slug);
    }

    /**
     * @return array{table: string, columns: array<string, array>, children: array<string, array>, errors: list<string>}
     *               columns/children are keyed by field key; columns hold column, path, field.
     */
    public function layout(object $type): array
    {
        $errors = [];
        $layout = $this->layoutFor($type->fields ?? [], self::tableName($type->slug), self::BASE_COLUMNS, $errors);
        $layout['errors'] = $errors;

        return $layout;
    }

    private function layoutFor(array $fields, string $table, array $reserved, array &$errors): array
    {
        $layout = ['table' => $table, 'columns' => [], 'children' => []];
        $used   = array_fill_keys($reserved, 'システムのカラム');

        $this->collect($fields, $layout, '', [], $used, $errors);

        if (strlen($this->db->DBPrefix . $table) > 64) {
            $errors[] = "テーブル名「{$table}」が長すぎます。短い名前にしてください。";
        }

        return $layout;
    }

    /**
     * Walk the fields of one table: groups add prefixed columns, repeaters add child tables.
     */
    private function collect(array $fields, array &$layout, string $prefix, array $path, array &$used, array &$errors): void
    {
        foreach ($fields as $field) {
            $column   = $prefix . $field['name'];
            $fullPath = [...$path, $field['name']];

            if ($field['type'] === 'group') {
                $this->collect($field['sub_fields'], $layout, $column . '_', $fullPath, $used, $errors);

                continue;
            }

            if ($field['type'] === 'repeater') {
                $child = $this->layoutFor($field['sub_fields'], $layout['table'] . '__' . $column, self::ROW_COLUMNS, $errors);

                $layout['children'][$field['key']] = $child + ['path' => $fullPath, 'field' => $field];

                continue;
            }

            if (isset($used[$column])) {
                $errors[] = $used[$column] === 'システムのカラム'
                    ? "「{$field['label']}」：「{$column}」はシステムで使用しているカラム名です（" . implode('、', array_keys(array_filter($used, static fn ($v) => $v === 'システムのカラム'))) . '）。別の名前にしてください。'
                    : "「{$field['label']}」の保存先カラム「{$column}」は、すでに{$used[$column]}で使用されています。どちらかの名前を変更してください。";

                continue;
            }
            if (strlen($column) > 64) {
                $errors[] = "「{$field['label']}」：カラム名「{$column}」が64文字を超えています。";
            }

            $used[$column]                      = "「{$field['label']}」";
            $layout['columns'][$field['key']] = ['column' => $column, 'path' => $fullPath, 'field' => $field];
        }
    }

    private function columnSpec(array $field): array
    {
        return match (self::STORAGE[$field['type']]) {
            'varchar'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'text'     => ['type' => 'TEXT', 'null' => true],
            'longtext' => ['type' => 'LONGTEXT', 'null' => true],
            'double'   => ['type' => 'DOUBLE', 'null' => true],
            'date'     => ['type' => 'DATE', 'null' => true],
            'bool'     => ['type' => 'TINYINT', 'constraint' => 1, 'null' => false, 'default' => 0],
            'json'     => ['type' => 'TEXT', 'null' => true],
        };
    }

    // ==================================================================
    // Checks before saving a definition
    // ==================================================================

    /**
     * Problems that block saving: naming conflicts and unsupported type changes.
     *
     * @return list<string>
     */
    public function check(object $type, ?object $old): array
    {
        $new    = $this->layout($type);
        $errors = $new['errors'];

        if (in_array($new['table'], self::SYSTEM_TABLES, true)) {
            $errors[] = "スラッグ「{$type->slug}」はCMS本体で使用されています。別のスラッグを指定してください。";
        }

        // A new table name must be free (unless it already belongs to this type).
        $oldTable = $old ? self::tableName($old->slug) : null;
        if ($new['table'] !== $oldTable && $this->tableExists($new['table'])) {
            $errors[] = "データベースにテーブル「{$new['table']}」がすでに存在します。別のスラッグを指定してください。";
        }

        if ($old !== null) {
            $oldFields = $this->fieldsByKey($old->fields ?? []);

            foreach ($this->fieldsByKey($type->fields ?? []) as $key => $field) {
                $before = $oldFields[$key] ?? null;

                if ($before === null || $before['type'] === $field['type']) {
                    continue;
                }
                if (! $this->canChangeType($before['type'], $field['type'])) {
                    $errors[] = sprintf(
                        '「%s」は%sから%sに変更できません（保存済みのデータが合わないため）。新しいフィールドを追加してください。',
                        $field['label'],
                        Fields::TYPES[$before['type']][0],
                        Fields::TYPES[$field['type']][0],
                    );
                }
            }
        }

        return $errors;
    }

    /**
     * Fields whose data would be deleted by saving this definition (removed fields and repeaters).
     *
     * @return list<string> labels
     */
    public function destructiveChanges(object $type, ?object $old): array
    {
        if ($old === null) {
            return [];
        }

        $new     = $this->fieldsByKey($type->fields ?? []);
        $removed = [];

        foreach ($this->fieldsByKey($old->fields ?? []) as $key => $field) {
            if (! isset($new[$key]) && $field['type'] !== 'group') {
                $removed[] = $field['label'];
            }
        }

        return $removed;
    }

    private function canChangeType(string $from, string $to): bool
    {
        $containers = Fields::CONTAINERS;

        if (in_array($from, $containers, true) || in_array($to, $containers, true)) {
            return false;
        }

        $a = self::STORAGE[$from];
        $b = self::STORAGE[$to];

        return $a === $b || (isset(self::WIDENING[$a], self::WIDENING[$b]) && self::WIDENING[$b] > self::WIDENING[$a]);
    }

    /**
     * @return array<string, array> every field at any depth, by key
     */
    private function fieldsByKey(array $fields): array
    {
        $out = [];

        foreach ($fields as $field) {
            $out[$field['key']] = $field;
            if (! empty($field['sub_fields'])) {
                $out += $this->fieldsByKey($field['sub_fields']);
            }
        }

        return $out;
    }

    // ==================================================================
    // Sync: apply a definition to the database
    // ==================================================================

    /**
     * Create/alter the tables of a content type. $old is the previous definition (null for a new type).
     * Passing the same definition twice repairs missing tables/columns (e.g. after copying a database).
     *
     * @return list<string> human-readable log of what changed
     */
    public function sync(object $type, ?object $old): array
    {
        $log = [];
        $this->syncTable($this->layout($type), $old ? $this->layout($old) : null, null, $log);
        $this->db->resetDataCache();

        return $log;
    }

    /**
     * Drop all tables of a content type (children first).
     */
    public function drop(object $type): void
    {
        $this->dropTables($this->layout($type));
        $this->db->resetDataCache();
    }

    private function syncTable(array $new, ?array $old, ?string $parentTable, array &$log): void
    {
        $table = $new['table'];

        if (! $this->tableExists($table)) {
            if ($old !== null && $old['table'] !== $table && $this->tableExists($old['table'])) {
                $this->forge->renameTable($old['table'], $table);
                $log[] = "テーブル {$old['table']} → {$table} に名前変更";
            } else {
                $this->createTable($new, $parentTable);
                $log[] = "テーブル {$table} を作成";
                $old = null;   // all columns were just created
            }
        }

        $this->db->resetDataCache();
        $existing = $this->db->getFieldNames($table);

        foreach ($new['columns'] as $key => $col) {
            $spec   = $this->columnSpec($col['field']);
            $before = $old['columns'][$key] ?? null;

            if ($before !== null && $before['column'] !== $col['column']
                && in_array($before['column'], $existing, true) && ! in_array($col['column'], $existing, true)) {
                $this->forge->modifyColumn($table, [$before['column'] => ['name' => $col['column']] + $spec]);
                $log[] = "カラム {$table}.{$before['column']} → {$col['column']} に名前変更";
            } elseif (! in_array($col['column'], $existing, true)) {
                $this->forge->addColumn($table, [$col['column'] => $spec]);
                $log[] = "カラム {$table}.{$col['column']} を追加";
            } elseif ($before !== null && self::STORAGE[$before['field']['type']] !== self::STORAGE[$col['field']['type']]) {
                $this->forge->modifyColumn($table, [$col['column'] => ['name' => $col['column']] + $spec]);
                $log[] = "カラム {$table}.{$col['column']} を {$spec['type']} 型に変更";
            }
        }

        // Columns of removed fields
        foreach (($old['columns'] ?? []) as $key => $col) {
            if (! isset($new['columns'][$key]) && in_array($col['column'], $existing, true)
                && ! in_array($col['column'], array_column($new['columns'], 'column'), true)) {
                $this->forge->dropColumn($table, $col['column']);
                $log[] = "カラム {$table}.{$col['column']} を削除";
            }
        }

        foreach ($new['children'] as $key => $child) {
            $this->syncTable($child, $old['children'][$key] ?? null, $table, $log);
        }

        // Tables of removed repeaters
        foreach (($old['children'] ?? []) as $key => $child) {
            if (! isset($new['children'][$key])) {
                $this->dropTables($child);
                $log[] = "テーブル {$child['table']} を削除";
            }
        }
    }

    private function createTable(array $layout, ?string $parentTable): void
    {
        $columns = [];
        foreach ($layout['columns'] as $col) {
            $columns[$col['column']] = $this->columnSpec($col['field']);
        }

        if ($parentTable === null) {
            $this->forge->addField([
                'id'               => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'title'            => ['type' => 'VARCHAR', 'constraint' => 255],
                'slug'             => ['type' => 'VARCHAR', 'constraint' => 191],
                'status'           => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'draft'],
                'author_id'        => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'meta_title'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'meta_description' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
                'published_at'     => ['type' => 'DATETIME', 'null' => true],
                'published_until'  => ['type' => 'DATETIME', 'null' => true],
            ] + $columns + [
                'created_at'       => ['type' => 'DATETIME', 'null' => true],
                'updated_at'       => ['type' => 'DATETIME', 'null' => true],
                'deleted_at'       => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addUniqueKey('slug');
            $this->forge->addKey(['status', 'published_at']);
            $this->forge->addForeignKey('author_id', 'users', 'id', 'CASCADE', 'SET NULL', $this->fkName());
        } else {
            $this->forge->addField([
                'id'        => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'parent_id' => ['type' => 'INT', 'unsigned' => true],
                'sort'      => ['type' => 'INT', 'default' => 0],
            ] + $columns);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey(['parent_id', 'sort']);
            // Rows disappear with their entry (or parent row).
            $this->forge->addForeignKey('parent_id', $parentTable, 'id', 'CASCADE', 'CASCADE', $this->fkName());
        }

        $this->forge->createTable($layout['table'], false, ['ENGINE' => 'InnoDB', 'DEFAULT CHARSET' => 'utf8mb4', 'COLLATE' => 'utf8mb4_unicode_ci']);
    }

    private function dropTables(array $layout): void
    {
        foreach ($layout['children'] as $child) {
            $this->dropTables($child);
        }
        $this->forge->dropTable($layout['table'], true);
    }

    /**
     * Foreign key names must be unique in the whole database and survive table renames.
     */
    private function fkName(): string
    {
        return $this->db->DBPrefix . 'fk_' . bin2hex(random_bytes(6));
    }

    /**
     * Fresh, prefix-aware check. (CodeIgniter's uncached tableExists($name, false) ignores the
     * table prefix, so it would miss "wd_news" when asked for "news".)
     */
    public function tableExists(string $table): bool
    {
        $this->db->resetDataCache();

        return $this->db->tableExists($table);
    }

    // ==================================================================
    // Values: nested field values ↔ table rows
    // ==================================================================

    /**
     * Column values for one table row from nested field values.
     */
    public function toRow(array $layout, array $values): array
    {
        $row = [];

        foreach ($layout['columns'] as $col) {
            $row[$col['column']] = $this->toDb($col['field'], $this->getPath($values, $col['path']));
        }

        return $row;
    }

    /**
     * Nested field values from a row, including repeater rows loaded from their tables.
     */
    public function values(array $layout, array $row): array
    {
        $values = [];

        foreach ($layout['columns'] as $col) {
            $this->setPath($values, $col['path'], $this->fromDb($col['field'], $row[$col['column']] ?? null));
        }

        foreach ($layout['children'] as $child) {
            $this->setPath($values, $child['path'], isset($row['id']) ? $this->loadRows($child, (int) $row['id']) : []);
        }

        return $values;
    }

    /**
     * Replace the repeater rows of an entry (or of a parent row).
     */
    public function saveChildren(array $layout, int $parentId, array $values): void
    {
        foreach ($layout['children'] as $child) {
            $table = $this->db->table($child['table']);
            $table->where('parent_id', $parentId)->delete();   // nested rows go with them (ON DELETE CASCADE)

            foreach (array_values((array) $this->getPath($values, $child['path'])) as $i => $rowValues) {
                $this->db->table($child['table'])->insert(['parent_id' => $parentId, 'sort' => $i] + $this->toRow($child, (array) $rowValues));
                $this->saveChildren($child, (int) $this->db->insertID(), (array) $rowValues);
            }
        }
    }

    private function loadRows(array $layout, int $parentId): array
    {
        $rows = $this->db->table($layout['table'])->where('parent_id', $parentId)->orderBy('sort')->orderBy('id')->get()->getResultArray();

        return array_map(fn (array $row) => $this->values($layout, $row), $rows);
    }

    private function toDb(array $field, mixed $value): mixed
    {
        return match ($field['type']) {
            'toggle'   => $value ? 1 : 0,
            'checkbox', 'relation' => $value ? json_encode(array_values(array_map('strval', (array) $value)), JSON_UNESCAPED_UNICODE) : null,
            'number'   => is_numeric($value) ? $value + 0 : null,
            'editor'   => $value === '' || $value === null ? null : $this->tokenizeUrls((string) $value),
            default    => $value === '' || $value === null ? null : (string) $value,
        };
    }

    private function fromDb(array $field, mixed $value): mixed
    {
        return match ($field['type']) {
            'toggle'   => (bool) $value,
            'checkbox', 'relation' => $value ? (json_decode((string) $value, true) ?: []) : [],
            'number'   => $value === null ? null : ((float) $value == (int) $value ? (int) $value : (float) $value),
            'editor'   => $value === null ? '' : $this->expandUrls((string) $value),
            default    => $value ?? '',
        };
    }

    /**
     * Store links/images to this site as {{base_url}}… so content keeps working after moving domains.
     */
    private function tokenizeUrls(string $html): string
    {
        return str_replace(rtrim(base_url(), '/') . '/', self::BASE_URL_TOKEN, $html);
    }

    private function expandUrls(string $html): string
    {
        return str_replace(self::BASE_URL_TOKEN, rtrim(base_url(), '/') . '/', $html);
    }

    private function getPath(array $values, array $path): mixed
    {
        foreach ($path as $segment) {
            if (! is_array($values) || ! array_key_exists($segment, $values)) {
                return null;
            }
            $values = $values[$segment];
        }

        return $values;
    }

    private function setPath(array &$values, array $path, mixed $value): void
    {
        $ref = &$values;
        foreach ($path as $segment) {
            if (! isset($ref[$segment]) || ! is_array($ref[$segment])) {
                $ref[$segment] = [];
            }
            $ref = &$ref[$segment];
        }
        $ref = $value;
    }

    /**
     * Fingerprint of all definitions, to notice when tables need repairing (e.g. after copying a database).
     *
     * @param iterable<object> $types
     */
    public static function hash(iterable $types): string
    {
        $parts = [];
        foreach ($types as $type) {
            $parts[$type->slug] = $type->fields ?? [];
        }
        ksort($parts);

        return md5(json_encode($parts));
    }
}
