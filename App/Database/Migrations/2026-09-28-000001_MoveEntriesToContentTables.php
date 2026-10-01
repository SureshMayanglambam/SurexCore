<?php

namespace App\Database\Migrations;

use App\Libraries\ContentSchema;
use CodeIgniter\Database\Migration;

/**
 * Each content type gets its own table (e.g. "news") with a real column per field,
 * replacing the shared "entries" table with JSON values. Existing entries are copied over
 * with their ids, dates and authors.
 */
class MoveEntriesToContentTables extends Migration
{
    private const BASE = [
        'id', 'title', 'slug', 'status', 'author_id', 'meta_title', 'meta_description',
        'published_at', 'created_at', 'updated_at', 'deleted_at',
    ];

    public function up(): void
    {
        $schema     = new ContentSchema($this->db);
        $hasEntries = $schema->tableExists('entries');

        foreach ($this->types() as $type) {
            $schema->sync($type, null);
            $layout = $schema->layout($type);

            if (! $hasEntries) {
                continue;
            }

            $rows = $this->db->table('entries')->where('type', $type->slug)->orderBy('id')->get()->getResultArray();

            foreach ($rows as $row) {
                $values = json_decode((string) $row['fields'], true) ?: [];

                $this->db->table($layout['table'])->insert(
                    array_intersect_key($row, array_flip(self::BASE)) + $schema->toRow($layout, $values),
                );
                $schema->saveChildren($layout, (int) $row['id'], $values);
            }

            // Never drop the old table unless every entry arrived.
            $copied = $this->db->table($layout['table'])->countAllResults();
            if ($copied !== count($rows)) {
                throw new \RuntimeException("Copying \"{$type->slug}\" entries failed: {$copied} of " . count($rows) . ' copied. The old entries table was kept.');
            }
        }

        if ($hasEntries) {
            $this->forge->dropTable('entries', true);
        }
    }

    public function down(): void
    {
        $this->forge->addField([
            'id'               => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'type'             => ['type' => 'VARCHAR', 'constraint' => 50],
            'title'            => ['type' => 'VARCHAR', 'constraint' => 255],
            'slug'             => ['type' => 'VARCHAR', 'constraint' => 191],
            'fields'           => ['type' => 'LONGTEXT', 'null' => true],
            'status'           => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'draft'],
            'author_id'        => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'meta_title'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'meta_description' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'published_at'     => ['type' => 'DATETIME', 'null' => true],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['type', 'slug']);
        $this->forge->addForeignKey('author_id', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('entries', true, ['ENGINE' => 'InnoDB']);

        $schema = new ContentSchema($this->db);

        foreach ($this->types() as $type) {
            $layout = $schema->layout($type);

            if (! $schema->tableExists($layout['table'])) {
                continue;
            }

            foreach ($this->db->table($layout['table'])->get()->getResultArray() as $row) {
                $this->db->table('entries')->insert(array_intersect_key($row, array_flip(self::BASE)) + [
                    'type'   => $type->slug,
                    'fields' => json_encode($schema->values($layout, $row), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
            }

            $schema->drop($type);
        }
    }

    private function types(): array
    {
        $types = $this->db->table('content_types')->get()->getResult();

        foreach ($types as $type) {
            $type->fields = json_decode((string) $type->fields, true) ?: [];
        }

        return $types;
    }
}
