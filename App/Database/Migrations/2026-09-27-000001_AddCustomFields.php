<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Custom fields (like ACF): each content type stores its field definitions as JSON,
 * and each entry stores its field values as JSON. The fixed content/excerpt columns go away;
 * entries.title stays, filled from the type's "title field" for lists and slugs.
 */
class AddCustomFields extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('content_types', [
            'fields'      => ['type' => 'LONGTEXT', 'null' => true, 'after' => 'description'],
            'title_field' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true, 'after' => 'fields'],
        ]);

        $this->forge->addColumn('entries', [
            'fields' => ['type' => 'LONGTEXT', 'null' => true, 'after' => 'slug'],
        ]);
        $this->forge->dropColumn('entries', ['content', 'excerpt']);
    }

    public function down(): void
    {
        $this->forge->addColumn('entries', [
            'content' => ['type' => 'LONGTEXT', 'null' => true, 'after' => 'slug'],
            'excerpt' => ['type' => 'TEXT', 'null' => true, 'after' => 'content'],
        ]);
        $this->forge->dropColumn('entries', 'fields');
        $this->forge->dropColumn('content_types', ['fields', 'title_field']);
    }
}
