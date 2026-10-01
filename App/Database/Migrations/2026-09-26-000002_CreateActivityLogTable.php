<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateActivityLogTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'           => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'user_id'      => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'user_name'    => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'action'       => ['type' => 'VARCHAR', 'constraint' => 50],
            'description'  => ['type' => 'VARCHAR', 'constraint' => 500],
            'subject_type' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'subject_id'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'ip_address'   => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'user_agent'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('created_at');
        $this->forge->addKey(['user_id', 'created_at']);
        $this->forge->addKey('action');
        $this->forge->createTable('activity_log', true, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('activity_log', true);
    }
}
