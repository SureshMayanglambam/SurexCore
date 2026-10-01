<?php

namespace App\Database\Migrations;

use App\Model\MediaModel;
use CodeIgniter\Database\Migration;

/**
 * Media library: one row per uploaded file (public/uploads/YYYY/MM/...).
 * Files uploaded before the library existed are registered here too.
 */
class CreateMediaTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'path'          => ['type' => 'VARCHAR', 'constraint' => 191],
            'original_name' => ['type' => 'VARCHAR', 'constraint' => 255],
            'mime'          => ['type' => 'VARCHAR', 'constraint' => 100],
            'size'          => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'width'         => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'height'        => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'user_id'       => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('path');
        $this->forge->addKey('created_at');
        $this->forge->createTable('media', true, ['ENGINE' => 'InnoDB']);

        // Register the files that are already there (the site logo is managed in Branding, not here).
        $model = new MediaModel($this->db);
        $names = $this->namesFromActivityLog();
        foreach ($this->existingUploads() as $path) {
            $model->register($path, $names[$path] ?? basename($path), null, date('Y-m-d H:i:s', (int) filemtime(FCPATH . $path)));
        }
    }

    /**
     * Original file names, recovered from "「name」をアップロードしました（path）" lines in the activity log.
     *
     * @return array<string, string> path => original name
     */
    private function namesFromActivityLog(): array
    {
        if (! $this->db->tableExists('activity_log')) {
            return [];
        }

        $names = [];
        $rows  = $this->db->table('activity_log')->select('description')->where('action', 'media.uploaded')->get()->getResultArray();
        foreach ($rows as $row) {
            if (preg_match('/^「(.+)」をアップロードしました（(uploads\/[^）]+)）$/u', $row['description'], $m)) {
                $names[$m[2]] = $m[1];
            }
        }

        return $names;
    }

    public function down(): void
    {
        $this->forge->dropTable('media', true);
    }

    /**
     * @return list<string> paths like "uploads/2026/09/abc.jpg", oldest first
     */
    private function existingUploads(): array
    {
        $root = FCPATH . 'uploads';
        if (! is_dir($root)) {
            return [];
        }

        $paths = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            $path = 'uploads/' . str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));

            if ($file->isFile() && ! str_starts_with($path, 'uploads/branding/') && ! in_array($file->getFilename(), ['index.html', '.htaccess'], true)) {
                $paths[$path] = $file->getMTime();
            }
        }
        asort($paths);

        return array_keys($paths);
    }
}
