<?php

namespace App\Filters;

use App\Libraries\ContentSchema;
use App\Model\ContentTypeModel;
use App\Model\SettingModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Keeps the database in step with the code, on any admin request:
 *  1. runs pending migrations when a newer version is deployed
 *     (the WordPress "Database Update Required" step, without the extra click);
 *  2. repairs content type tables when their definitions changed outside the admin panel
 *     (e.g. the database was copied from another server).
 */
class DbUpgrade implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $installer = service('installer');

        if ($installer->needsUpgrade()) {
            try {
                $installer->migrate();
                $installer->notice = ['success', 'データベースを最新バージョンに更新しました。'];
                log_activity('system.upgrade', 'データベースをバージョン ' . $installer->latestMigrationVersion() . ' に更新しました');
            } catch (\Throwable $e) {
                log_message('critical', 'Database upgrade failed: {message}', ['message' => $e->getMessage()]);
                $installer->notice = ['error', 'データベースの更新に失敗しました。詳細は writable/logs を確認してください。'];

                return null;
            }
        }

        $this->syncContentTables();

        return null;
    }

    private function syncContentTables(): void
    {
        $types = model(ContentTypeModel::class)->allBySlug();
        $hash  = ContentSchema::hash($types);

        if (setting('content_schema_hash') === $hash) {
            return;
        }

        try {
            $changes = [];
            foreach ($types as $type) {
                $changes = [...$changes, ...service('contentSchema')->sync($type, $type)];
            }
            model(SettingModel::class)->put('content_schema_hash', $hash);

            if ($changes !== []) {
                log_activity('system.schema', 'コンテンツテーブルを修復しました: ' . implode('; ', $changes));
            }
        } catch (\Throwable $e) {
            log_message('critical', 'Content table repair failed: {message}', ['message' => $e->getMessage()]);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
