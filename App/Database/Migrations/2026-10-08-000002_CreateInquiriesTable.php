<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * お問い合わせ: form submissions saved in the admin (only while 一般設定 → 「お問い合わせを保存する」 is on).
 */
class CreateInquiriesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'form'            => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'contact'],
            'name'            => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'email'           => ['type' => 'VARCHAR', 'constraint' => 191, 'null' => true],
            'fields'          => ['type' => 'TEXT', 'null' => true],
            'attachment_name' => ['type' => 'VARCHAR', 'constraint' => 191, 'null' => true],
            'attachment_file' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'ip_address'      => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'read_at'         => ['type' => 'DATETIME', 'null' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('created_at');
        $this->forge->addKey(['form', 'read_at']);
        $this->forge->createTable('inquiries', true, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('inquiries', true);
    }
}
