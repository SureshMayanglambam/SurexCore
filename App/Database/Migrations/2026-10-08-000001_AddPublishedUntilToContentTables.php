<?php

namespace App\Database\Migrations;

use App\Libraries\ContentSchema;
use CodeIgniter\Database\Migration;

/**
 * 公開終了日時: entries can have an end of their publishing period (empty = no end).
 * Adds published_until to every existing content type table; new tables get it from ContentSchema.
 */
class AddPublishedUntilToContentTables extends Migration
{
    public function up(): void
    {
        foreach ($this->db->table('content_types')->select('slug')->get()->getResultArray() as $type) {
            $table = ContentSchema::tableName($type['slug']);

            if ($this->db->tableExists($table) && ! $this->db->fieldExists('published_until', $table)) {
                $this->forge->addColumn($table, [
                    'published_until' => ['type' => 'DATETIME', 'null' => true, 'after' => 'published_at'],
                ]);
            }
        }
    }

    public function down(): void
    {
        foreach ($this->db->table('content_types')->select('slug')->get()->getResultArray() as $type) {
            $table = ContentSchema::tableName($type['slug']);

            if ($this->db->tableExists($table) && $this->db->fieldExists('published_until', $table)) {
                $this->forge->dropColumn($table, 'published_until');
            }
        }
    }
}
