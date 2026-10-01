<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Optional frontend template used by the entry preview (e.g. "frontend.news.detail").
 * Empty = frontend.{slug}.detail.
 */
class AddPreviewViewToContentTypes extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('content_types', [
            'preview_view' => ['type' => 'VARCHAR', 'constraint' => 191, 'null' => true, 'after' => 'title_field'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('content_types', 'preview_view');
    }
}
