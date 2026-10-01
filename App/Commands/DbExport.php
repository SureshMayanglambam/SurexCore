<?php

namespace App\Commands;

use App\Admin\Backup;
use App\Libraries\DatabaseExport;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * php spark db:export <file>
 * The same SQL dump as 設定 → バックアップ (used by deploy.sh full): importing it replaces every table
 * with this database's data, except the activity log, which the importing site keeps.
 */
class DbExport extends BaseCommand
{
    protected $group       = 'SurexCore';
    protected $name        = 'db:export';
    protected $description = 'Export the database as SQL (activity log excluded) for import on another server.';
    protected $usage       = 'db:export <file>';

    public function run(array $params)
    {
        $file = $params[0] ?? null;
        if ($file === null) {
            CLI::error('Usage: php spark db:export <file>');

            return EXIT_USER_INPUT;
        }

        (new DatabaseExport(db_connect()))->toFile($file, Backup::KEEP_EXISTING);
        CLI::write('Exported to ' . $file, 'green');

        return EXIT_SUCCESS;
    }
}
