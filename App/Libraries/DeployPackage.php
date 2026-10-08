<?php

namespace App\Libraries;

use Config\Database;

/**
 * Zip of the files a server needs (設定 → バックアップ → デプロイ). Extract it into the document root.
 *
 *   site: program + vendor/ + public/uploads/ + database + .env template  (first deploy)
 *   code: program + vendor/ only                                           (updates)
 *
 * Only these top-level entries are packed, so local files (.env, .git, docker/, deploy/, tests/,
 * notes, ...) never end up on the server.
 */
class DeployPackage
{
    private const TOP_LEVEL = ['.htaccess', 'App', 'View', 'routes', 'public', 'vendor', 'writable', 'spark', 'preload.php', 'composer.json', 'composer.lock'];

    /** writable/: only the folders (their index.html / .htaccess), never logs, cache, sessions or backups. */
    private const WRITABLE_KEEP = '#^writable/([^/]+/)?(index\.html|\.htaccess)$#';

    /** Never packed, anywhere. */
    private const SKIP_NAMES = ['.DS_Store', 'Thumbs.db', '.gitkeep'];

    /** Already compressed: stored as they are (faster). */
    private const STORED = '/\.(jpe?g|png|gif|webp|ico|woff2?|zip|pdf|docx|xlsx|pptx)$/i';

    private \ZipArchive $zip;

    public function build(string $zipFile, string $type, string $sqlFile = ''): void
    {
        $this->zip = new \ZipArchive();
        $this->zip->open($zipFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $withData = $type === 'site';

        foreach (self::TOP_LEVEL as $entry) {
            $path = ROOTPATH . $entry;
            if (is_file($path)) {
                $this->add($path, $entry);
            } elseif (is_dir($path)) {
                $this->addTree($path, $entry, $withData);
            }
        }

        if ($withData) {
            // The imported database is installed already: no installer on the server.
            if (is_file(WRITEPATH . 'installed.lock')) {
                $this->add(WRITEPATH . 'installed.lock', 'writable/installed.lock');
            }
            $this->zip->addFile($sqlFile, '_deploy/database.sql');
            $this->zip->addFromString('_deploy/env-server.txt', $this->envTemplate());
            $this->zip->addFromString('_deploy/README.txt', $this->steps(true));
        } else {
            $this->zip->addFromString('_deploy/README.txt', $this->steps(false));
        }

        $this->zip->close();
    }

    private function addTree(string $dir, string $prefix, bool $withData): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if (! $file->isFile() || in_array($file->getFilename(), self::SKIP_NAMES, true)) {
                continue;
            }
            $local = $prefix . '/' . str_replace('\\', '/', substr($file->getPathname(), strlen($dir) + 1));

            if (str_starts_with($local, 'writable/')) {
                // Folders only, plus the attachments of saved お問い合わせ when data is included.
                if (! preg_match(self::WRITABLE_KEEP, $local) && ! ($withData && str_starts_with($local, 'writable/uploads/inquiries/'))) {
                    continue;
                }
            }
            if (str_starts_with($local, 'public/uploads/') && ! $withData && ! in_array($file->getFilename(), ['.htaccess', 'index.html'], true)) {
                continue;
            }

            $this->add($file->getPathname(), $local);
        }
    }

    private function add(string $path, string $local): void
    {
        $this->zip->addFile($path, $local);
        if (preg_match(self::STORED, $local)) {
            $this->zip->setCompressionName($local, \ZipArchive::CM_STORE);
        }
    }

    /**
     * .env for the server: this site's settings, a new encryption key, ●● where the server differs.
     */
    private function envTemplate(): string
    {
        $cms   = config('Cms');
        $email = config('Email');
        $db    = config(Database::class)->default;

        return implode("\n", [
            '# Server .env — fill in every ●●, save it as .env next to App/ (not inside public/).',
            '# A new encryption key was generated for this site. Keep this file private.',
            '',
            'CI_ENVIRONMENT = production',
            '',
            "app.baseURL = 'https://●●/'",
            "app.appTimezone = '" . config('App')->appTimezone . "'",
            'app.forceGlobalSecureRequests = true',
            '',
            "cms.appName = '" . $cms->appName . "'",
            "cms.adminEmail = '" . $cms->adminEmail . "'",
            "cms.adminPath = '" . $cms->adminPath . "'",
            '',
            "database.default.hostname = 'localhost'",
            "database.default.database = '●●'",
            "database.default.username = '●●'",
            "database.default.password = '●●'",
            'database.default.DBDriver = MySQLi',
            "database.default.DBPrefix = '" . $db['DBPrefix'] . "'",
            'database.default.port = 3306',
            '',
            'encryption.key = hex2bin:' . bin2hex(random_bytes(32)),
            '',
            "email.protocol = 'smtp'",
            "email.fromEmail = '" . $email->fromEmail . "'",
            "email.fromName = '" . $email->fromName . "'",
            "email.SMTPHost = '" . ($email->SMTPHost ?: '●●') . "'",
            "email.SMTPUser = '" . ($email->SMTPUser ?: '●●') . "'",
            "email.SMTPPass = '●●'",
            'email.SMTPPort = ' . (int) $email->SMTPPort,
            "email.SMTPCrypto = '" . $email->SMTPCrypto . "'",
            "email.mailType = 'html'",
            '',
        ]);
    }

    private function steps(bool $first): string
    {
        $common = "1. サーバーの公開フォルダ（public_html など）に、このzipの中身をすべてアップロード・展開します。\n"
            . "   ・ファイルマネージャーがある場合：zipをアップロードしてサーバー上で展開（隠しファイル .htaccess も含まれます）\n"
            . "   ・FTPのみの場合：PCで展開し、中身をすべてアップロード（「隠しファイルを表示」をオン）\n";

        if (! $first) {
            return "更新用パッケージ（プログラムのみ）\n\n" . $common
                . "2. サーバーの .env・データベース・画像（public/uploads）はそのまま残ります。\n"
                . "3. 管理画面を開くと、必要なデータベース更新が自動で実行されます。\n"
                . "4. _deploy フォルダは削除してください。\n";
        }

        return "初回公開用パッケージ（サイト一式）\n\n"
            . "0. サーバーで空のデータベースを作成し、データベース名・ユーザー名・パスワードを控えます。\n" . $common
            . "2. phpMyAdmin でそのデータベースを選択 → インポート → _deploy/database.sql\n"
            . "3. _deploy/env-server.txt の ●● を入力し、App/ と同じ階層に「.env」という名前で保存します。\n"
            . "   （サブフォルダに公開する場合 app.baseURL は https://ドメイン/フォルダ名/）\n"
            . "4. サイトと管理画面（/" . config('Cms')->adminPath . "/login）を確認します。\n"
            . "5. 確認後、_deploy フォルダを削除してください。\n";
    }
}
