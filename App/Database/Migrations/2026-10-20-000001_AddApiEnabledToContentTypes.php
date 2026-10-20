<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Settings → Content Types → "Expose REST API": published entries readable at /api/{slug}.
 * Off by default, so nothing becomes public by surprise.
 */
class AddApiEnabledToContentTypes extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('content_types', [
            'api_enabled' => ['type' => 'TINYINT', 'constraint' => 1, 'null' => false, 'default' => 0, 'after' => 'preview_view'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('content_types', 'api_enabled');
    }
}
