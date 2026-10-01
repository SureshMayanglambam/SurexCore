<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Content types (News, Store, ...) are created from the admin panel, like WordPress custom post types.
 * Entries of every type share one `entries` table, keyed by entries.type = content_types.slug.
 */
class CreateContentTypesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 100],
            'singular'    => ['type' => 'VARCHAR', 'constraint' => 100],
            'slug'        => ['type' => 'VARCHAR', 'constraint' => 50],
            'icon'        => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'file-earmark-text'],
            'description' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'sort_order'  => ['type' => 'INT', 'default' => 0],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('slug');
        $this->forge->createTable('content_types', true, ['ENGINE' => 'InnoDB']);

        // Entries replace the fixed "posts" table.
        $this->forge->renameTable('posts', 'entries');
        $this->forge->modifyColumn('entries', [
            'type' => ['name' => 'type', 'type' => 'VARCHAR', 'constraint' => 50],
        ]);
    }

    public function down(): void
    {
        $this->forge->modifyColumn('entries', [
            'type' => ['name' => 'type', 'type' => 'VARCHAR', 'constraint' => 20, 'default' => 'post'],
        ]);
        $this->forge->renameTable('entries', 'posts');
        $this->forge->dropTable('content_types', true);
    }
}
