<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Login ID (username) for WebAdmins, optional email, and the admin/webadmin roles.
 */
class AddUsernameAndRoles extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('users', [
            'username' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true, 'after' => 'name'],
        ]);
        $this->forge->modifyColumn('users', [
            'email' => ['name' => 'email', 'type' => 'VARCHAR', 'constraint' => 191, 'null' => true],
            'role'  => ['name' => 'role', 'type' => 'VARCHAR', 'constraint' => 20, 'null' => false, 'default' => 'webadmin'],
        ]);
        $this->forge->addKey('username', false, true, 'users_username');
        $this->forge->processIndexes('users');

        $this->db->table('users')->where('role !=', 'admin')->update(['role' => 'webadmin']);
    }

    public function down(): void
    {
        $this->forge->dropKey('users', 'users_username');
        $this->forge->dropColumn('users', 'username');
        $this->db->table('users')->where('role', 'webadmin')->update(['role' => 'editor']);
    }
}
