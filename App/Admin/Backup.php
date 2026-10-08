<?php

namespace App\Admin;

use App\Libraries\DatabaseExport;
use App\Libraries\DeployPackage;
use App\Model\SettingModel;
use CodeIgniter\HTTP\DownloadResponse;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * 設定 → バックアップ (Admin only): download the database as .sql, or the database + public/uploads as .zip.
 * The files are built in writable/backups/ and deleted right after the download.
 * Restore: import the .sql with phpMyAdmin, put uploads/ back into public/uploads/.
 */
class Backup extends AdminController
{
    /** Tables left as they are when the backup is imported (see DatabaseExport). */
    public const KEEP_EXISTING = ['activity_log'];

    public function index(): string
    {
        return $this->render('admin.backup.index', [
            'canZip'     => class_exists(\ZipArchive::class),
            'lastBackup' => setting('last_backup_at', ''),
        ]);
    }

    public function download(): DownloadResponse|RedirectResponse
    {
        $withUploads = $this->request->getPost('type') === 'full';

        if ($withUploads && ! class_exists(\ZipArchive::class)) {
            return redirect()->route('admin.backup')->with('error', 'サーバーで ZIP 機能（PHP zip 拡張）が使えないため、データベースのみ保存できます。');
        }

        @set_time_limit(0);
        $dir = WRITEPATH . 'backups/';
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $this->removeOldFiles($dir);

        // ASCII file name from the domain, e.g. example-com-backup-20261001-120000.sql
        $name = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower((string) parse_url(base_url(), PHP_URL_HOST))), '-') ?: 'site';
        $base = $name . '-backup-' . date('Ymd-His');
        $sql  = $dir . $base . '.sql';

        (new DatabaseExport(db_connect()))->toFile($sql, self::KEEP_EXISTING);
        $file = $sql;

        if ($withUploads) {
            $file = $dir . $base . '.zip';
            $this->zip($file, $sql);
            @unlink($sql);
        }

        model(SettingModel::class)->put('last_backup_at', date('Y-m-d H:i'), false);
        log_activity('backup.downloaded', $withUploads ? 'バックアップ（データベース＋アップロード）をダウンロードしました' : 'バックアップ（データベース）をダウンロードしました');

        // Delete the file once it has been sent.
        register_shutdown_function(static fn () => @unlink($file));

        return $this->response->download($file, null)->setFileName(basename($file));
    }

    /**
     * デプロイ用パッケージ: the files to upload to a server, in one zip (see App\Libraries\DeployPackage).
     *   site: program + vendor + data + .env template (first deploy)    code: program + vendor (updates)
     */
    public function package(): DownloadResponse|RedirectResponse
    {
        $type = $this->request->getPost('type') === 'code' ? 'code' : 'site';

        if (! class_exists(\ZipArchive::class)) {
            return redirect()->route('admin.backup')->with('error', 'サーバーで ZIP 機能（PHP zip 拡張）が使えないため、パッケージを作成できません。');
        }
        if (! is_file(ROOTPATH . 'vendor/autoload.php')) {
            return redirect()->route('admin.backup')->with('error', 'vendor フォルダがありません。composer install を実行してください。');
        }

        @set_time_limit(0);
        $dir = WRITEPATH . 'backups/';
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $this->removeOldFiles($dir);

        $name = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower((string) parse_url(base_url(), PHP_URL_HOST))), '-') ?: 'site';
        $base = $dir . $name . '-backup-deploy-' . $type . '-' . date('Ymd-His');
        $sql  = '';

        if ($type === 'site') {
            $sql = $base . '.sql';
            (new DatabaseExport(db_connect()))->toFile($sql, self::KEEP_EXISTING);
        }

        $file = $base . '.zip';
        (new DeployPackage())->build($file, $type, $sql);
        if ($sql !== '') {
            @unlink($sql);
        }

        log_activity('backup.deploy', $type === 'site' ? 'デプロイ用パッケージ（初回公開用）をダウンロードしました' : 'デプロイ用パッケージ（更新用）をダウンロードしました');
        register_shutdown_function(static fn () => @unlink($file));

        return $this->response->download($file, null)->setFileName(str_replace('-backup-deploy-', '-deploy-', basename($file)));
    }

    /**
     * database.sql + uploads/ (public/uploads). Images are stored without compression: they are compressed already.
     */
    private function zip(string $zipFile, string $sqlFile): void
    {
        $zip = new \ZipArchive();
        $zip->open($zipFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFile($sqlFile, 'database.sql');
        $zip->addFromString('README.txt', "復元方法\n1. database.sql を phpMyAdmin でインポート\n2. uploads フォルダを public/uploads/ に戻す\n3. inquiries フォルダ（お問い合わせの添付ファイル）があれば writable/uploads/inquiries/ に戻す\n");

        // Uploaded media, and the attachments of saved お問い合わせ.
        foreach ([FCPATH . 'uploads' => 'uploads/', WRITEPATH . 'uploads/inquiries' => 'inquiries/'] as $root => $prefix) {
            if (! is_dir($root)) {
                continue;
            }
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
            foreach ($files as $f) {
                if ($f->isFile()) {
                    $local = $prefix . str_replace('\\', '/', substr($f->getPathname(), strlen($root) + 1));
                    $zip->addFile($f->getPathname(), $local);
                    if (preg_match('/\.(jpe?g|png|gif|webp|zip|docx|xlsx|pptx)$/i', $local)) {
                        $zip->setCompressionName($local, \ZipArchive::CM_STORE);
                    }
                }
            }
        }
        $zip->close();
    }

    /**
     * Leftovers of interrupted downloads (older than an hour).
     */
    private function removeOldFiles(string $dir): void
    {
        foreach (glob($dir . '*-backup-*') ?: [] as $old) {
            if (filemtime($old) < time() - HOUR) {
                @unlink($old);
            }
        }
    }
}
