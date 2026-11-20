<?php

namespace App\Admin;

use App\Libraries\DatabaseExport;
use App\Libraries\DeployPackage;
use App\Libraries\SiteMigration;
use App\Model\SettingModel;
use CodeIgniter\HTTP\DownloadResponse;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

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
            'migrations' => SiteMigration::available() ? (new SiteMigration())->files() : [],
            'canEncrypt' => SiteMigration::canEncrypt(),
            'chunkSize'  => $this->chunkSize(),
        ]);
    }

    public function download(): DownloadResponse|RedirectResponse
    {
        $type        = $this->request->getPost('type');
        $withUploads = $type === 'full';

        if ($type === 'uploads') {
            return $this->downloadUploads();
        }
        if ($withUploads && ! class_exists(\ZipArchive::class)) {
            return redirect()->route('admin.backup')->with('error', lang('Admin.flash.zip_db_only'));
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
            return redirect()->route('admin.backup')->with('error', lang('Admin.flash.zip_no_package'));
        }
        if (! is_file(ROOTPATH . 'vendor/autoload.php')) {
            return redirect()->route('admin.backup')->with('error', lang('Admin.flash.no_vendor'));
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

    // ================================================================== migration (like All-in-One WP Migration)

    /**
     * Export: build a migration file (kept in the list below the form) and download it.
     */
    public function migrationExport(): DownloadResponse|RedirectResponse
    {
        if (! SiteMigration::available()) {
            return redirect()->route('admin.backup')->with('error', lang('Admin.flash.zip_no_package'));
        }

        $password = (string) $this->request->getPost('password');
        try {
            $file = (new SiteMigration())->export(
                $this->request->getPost('uploads') === '1',
                $this->request->getPost('code') === '1',
                $password,
            );
        } catch (RuntimeException $e) {
            return redirect()->route('admin.backup')->with('error', lang('Admin.migration.err_' . $e->getMessage()));
        }

        log_activity('backup.migration_export', '移行ファイルを作成しました（' . basename($file) . ($password !== '' ? '・パスワード付き' : '') . '）');

        return $this->response->download($file, null)->setFileName(basename($file));
    }

    /**
     * Chunked upload of a migration file (works under any upload_max_filesize). JSON in, JSON out.
     * Chunks must arrive in order; the last one turns the .part file into a .zip in writable/migrations/.
     */
    public function migrationUpload(): ResponseInterface
    {
        $id    = (string) $this->request->getPost('upload_id');
        $index = (int) $this->request->getPost('index');
        $total = (int) $this->request->getPost('total');
        $name  = (string) $this->request->getPost('name');
        $chunk = $this->request->getFile('chunk');

        if (! preg_match('/^[a-f0-9]{32}$/', $id) || $index < 0 || $total < 1 || $index >= $total || $chunk === null || ! $chunk->isValid()) {
            return $this->json(['error' => lang('Admin.migration.err_upload')], 400);
        }

        if (! is_dir(SiteMigration::DIR)) {
            mkdir(SiteMigration::DIR, 0755, true);
        }
        $part = SiteMigration::DIR . '.upload-' . $id . '.part';
        $have = is_file($part . '.n') ? (int) file_get_contents($part . '.n') : 0;
        if ($index !== $have) {
            return $this->json(['error' => lang('Admin.migration.err_upload')], 409);
        }

        file_put_contents($part, file_get_contents($chunk->getTempName()), FILE_APPEND | LOCK_EX);
        file_put_contents($part . '.n', (string) ($index + 1));

        if ($index + 1 < $total) {
            return $this->json(['received' => $index + 1]);
        }

        // Last chunk: keep it only if it really is a SurexCore migration file.
        @unlink($part . '.n');
        $base  = preg_replace('/[^A-Za-z0-9._-]+/', '-', pathinfo($name, PATHINFO_FILENAME)) ?: 'migration';
        $final = SiteMigration::DIR . substr($base, 0, 120) . '.zip';
        for ($i = 2; is_file($final); $i++) {
            $final = SiteMigration::DIR . substr($base, 0, 110) . '-' . $i . '.zip';
        }
        rename($part, $final);

        if ((new SiteMigration())->manifest($final) === null) {
            @unlink($final);

            return $this->json(['error' => lang('Admin.migration.err_not_migration')], 422);
        }
        log_activity('backup.migration_upload', '移行ファイルをアップロードしました（' . basename($final) . '）');

        return $this->json(['done' => true, 'name' => basename($final)]);
    }

    public function migrationDownload(string $name): DownloadResponse|RedirectResponse
    {
        $path = SiteMigration::path($name);

        return $path === null
            ? redirect()->route('admin.backup')->with('error', lang('Admin.migration.err_missing'))
            : $this->response->download($path, null)->setFileName($name);
    }

    public function migrationDelete(string $name): RedirectResponse
    {
        if ($path = SiteMigration::path($name)) {
            unlink($path);
            log_activity('backup.migration_delete', '移行ファイルを削除しました（' . $name . '）');
        }

        return redirect()->route('admin.backup')->with('success', lang('Admin.migration.deleted'));
    }

    /**
     * Import, step 1: check + unpack (nothing changes yet). The page then runs the other steps one by one.
     */
    public function importStart(): ResponseInterface
    {
        $path = SiteMigration::path((string) $this->request->getPost('file'));
        if ($path === null) {
            return $this->json(['error' => lang('Admin.migration.err_missing')], 404);
        }

        try {
            $migration = new SiteMigration();
            $job       = $migration->startImport($path, (string) $this->request->getPost('password'));
        } catch (RuntimeException $e) {
            return $this->json(['error' => $this->migrationError($e)], 422);
        }

        $steps = $this->request->getPost('backup') === '1' ? ['backup', 'uploads', 'code', 'database'] : ['uploads', 'code', 'database'];

        return $this->json(['job' => $job, 'steps' => $steps]);
    }

    /**
     * Import, next step: backup → uploads → code → database (+ finish). The database goes last: after it,
     * the user accounts are the imported ones, so this session ends and everyone logs in again.
     */
    public function importStep(): ResponseInterface
    {
        $job       = (string) $this->request->getPost('job');
        $step      = (string) $this->request->getPost('step');
        $migration = new SiteMigration();

        try {
            $info = $migration->job($job);

            switch ($step) {
                case 'backup':
                    $file = $migration->export(true, true, '', 'before-import');
                    $migration->setJobBackup($job, $file);

                    return $this->json(['ok' => true]);

                case 'uploads':
                    $migration->importUploads($job);

                    return $this->json(['ok' => true]);

                case 'code':
                    $migration->importCode($job);

                    return $this->json(['ok' => true]);

                case 'database':
                    try {
                        $migration->importDatabase($job);
                    } catch (RuntimeException $e) {
                        // Put the previous database back, so this site keeps working and you can still log in.
                        if (! empty($info['backup']) && ($backup = SiteMigration::path($info['backup']))) {
                            $migration->restoreDatabase($backup);
                            $migration->abortImport($job);

                            return $this->json(['error' => lang('Admin.migration.err_rolled_back', [$e->getMessage()])], 500);
                        }
                        throw $e;
                    }
                    $manifest = $migration->finishImport($job);

                    log_activity('backup.migration_import', '移行ファイルをインポートしました（' . ($manifest['source_url'] ?? '') . '・SurexCore ' . ($manifest['version'] ?? '') . '）');

                    // The accounts are now the imported site's: end this session, log in again.
                    session()->remove(['user_id', 'auth_fp']);
                    session()->regenerate(true);
                    session()->setFlashdata('success', lang('Admin.migration.imported'));

                    return $this->json(['ok' => true, 'redirect' => url_to('admin.login')]);
            }
        } catch (RuntimeException $e) {
            return $this->json(['error' => $this->migrationError($e)], 500);
        }

        return $this->json(['error' => lang('Admin.migration.err_upload')], 400);
    }

    private function migrationError(RuntimeException $e): string
    {
        [$code, $detail] = array_pad(explode(': ', $e->getMessage(), 2), 2, '');
        $line            = lang('Admin.migration.err_' . $code);

        return str_starts_with($line, 'Admin.') ? lang('Admin.migration.err_generic', [$e->getMessage()]) : ($detail !== '' ? $line . ' ' . $detail : $line);
    }

    private function json(array $data, int $status = 200): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON($data);
    }

    /**
     * Upload chunk size: safely under this server's upload_max_filesize / post_max_size (max 8 MB).
     */
    private function chunkSize(): int
    {
        $bytes = static function (string $v): int {
            $n = (int) $v;

            return match (strtolower(substr(trim($v), -1))) {
                'g' => $n * 1024 ** 3, 'm' => $n * 1024 ** 2, 'k' => $n * 1024, default => $n,
            };
        };
        $limit = min(array_filter([$bytes((string) ini_get('upload_max_filesize')), $bytes((string) ini_get('post_max_size'))]) ?: [2 * 1024 ** 2]);

        return max(256 * 1024, min(8 * 1024 ** 2, $limit - 64 * 1024));
    }

    /**
     * Uploads backup: public/uploads (+ contact attachments) as a .zip.
     */
    private function downloadUploads(): DownloadResponse|RedirectResponse
    {
        if (! class_exists(\ZipArchive::class)) {
            return redirect()->route('admin.backup')->with('error', lang('Admin.flash.zip_no_package'));
        }
        @set_time_limit(0);
        $dir = WRITEPATH . 'backups/';
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $this->removeOldFiles($dir);

        $name = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower((string) parse_url(base_url(), PHP_URL_HOST))), '-') ?: 'site';
        $file = $dir . $name . '-backup-uploads-' . date('Ymd-His') . '.zip';
        $this->zip($file, '');

        log_activity('backup.downloaded', 'バックアップ（アップロード）をダウンロードしました');
        register_shutdown_function(static fn () => @unlink($file));

        return $this->response->download($file, null)->setFileName(str_replace('-backup-uploads-', '-uploads-', basename($file)));
    }

    /**
     * database.sql (if given) + uploads/ (public/uploads). Images are stored without compression: they are compressed already.
     */
    private function zip(string $zipFile, string $sqlFile): void
    {
        $zip = new \ZipArchive();
        $zip->open($zipFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        if ($sqlFile !== '') {
            $zip->addFile($sqlFile, 'database.sql');
        }
        $zip->addFromString('README.txt', lang('Admin.backup.readme'));

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
