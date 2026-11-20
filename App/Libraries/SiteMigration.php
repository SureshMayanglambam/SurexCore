<?php

namespace App\Libraries;

use RuntimeException;

/**
 * Site migration, like All-in-One WP Migration: one .zip with everything a site is made of —
 * database, uploads, contact attachments and the site's own code — but not SurexCore itself.
 *
 *   Export on production → install the latest SurexCore anywhere (local, STG, a new server) →
 *   Import: the data and site code arrive, the target keeps its own .env (database, URL, mail),
 *   the old site URL is replaced with this one, a different table prefix is handled, and the
 *   database is brought up to the installed version. Importing into a newer SurexCore = upgrade.
 *
 * Files live in writable/migrations/ (never served, not in deploy packages, can be copied there by FTP).
 * Optional AES-256 password. Import replaces everything, including the user accounts.
 */
class SiteMigration
{
    public const DIR = WRITEPATH . 'migrations' . DIRECTORY_SEPARATOR;

    /** Version of the file layout (manifest.format). */
    public const FORMAT = 1;

    /** Table prefix placeholder in database.sql, replaced with the importing site's prefix. */
    private const PREFIX_TOKEN = '__SX_PREFIX__';

    /** The site's own code: whole folders … */
    public const CODE_DIRS = ['View/frontend', 'public/assets/frontend', 'App/Controller/Http', 'App/Model/Http'];

    /** … and single files (everything else is SurexCore and comes from the installed version). */
    public const CODE_FILES = [
        'routes/web.php', 'App/Config/Sitemap.php', 'App/Config/Pager.php',
        'View/system/errors/404.blade.php', 'View/system/maintenance.blade.php',
        'public/favicon.ico', 'public/apple-touch-icon.png',
    ];

    /** Pagination designs of the site (View/system/pager/*.php) — but not the admin's own bootstrap.php. */
    private const PAGER_DIR = 'View/system/pager';

    public static function available(): bool
    {
        return class_exists(\ZipArchive::class);
    }

    public static function canEncrypt(): bool
    {
        return self::available() && defined('ZipArchive::EM_AES_256') && method_exists(\ZipArchive::class, 'setEncryptionName');
    }

    // ================================================================== export

    /**
     * Build a migration file in writable/migrations/ and return its path.
     */
    public function export(bool $withUploads = true, bool $withCode = true, string $password = '', string $label = ''): string
    {
        $this->ensureDir();
        @set_time_limit(0);

        $db     = db_connect();
        $prefix = $db->getPrefix();
        if ($prefix === '') {
            throw new RuntimeException('no_prefix');
        }

        $host = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower((string) parse_url(base_url(), PHP_URL_HOST))), '-') ?: 'site';
        $file = self::DIR . $host . ($label !== '' ? '-' . $label : '') . '-' . date('Ymd-His') . '.zip';

        // database.sql with the table prefix replaced by a placeholder
        $raw = self::DIR . '.export-' . bin2hex(random_bytes(6)) . '.sql';
        $sql = $raw . '.tokens';
        (new DatabaseExport($db))->toFile($raw);
        $in  = fopen($raw, 'rb');
        $out = fopen($sql, 'wb');
        while (($line = fgets($in)) !== false) {
            fwrite($out, str_replace('`' . $prefix, '`' . self::PREFIX_TOKEN, $line));
        }
        fclose($in);
        fclose($out);
        @unlink($raw);

        $manifest = [
            'format'     => self::FORMAT,
            'app'        => 'SurexCore',
            'version'    => config('Cms')->version,
            'site_name'  => setting('site_name', config('Cms')->appName),
            'source_url' => base_url(),
            'created'    => date(DATE_ATOM),
            'includes'   => ['database' => true, 'uploads' => $withUploads, 'code' => $withCode],
            'encrypted'  => $password !== '',
        ];

        $zip = new \ZipArchive();
        if ($zip->open($file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            @unlink($sql);
            throw new RuntimeException('cannot_write');
        }
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $zip->addFile($sql, 'database.sql');

        if ($withUploads) {
            $this->addTree($zip, FCPATH . 'uploads', 'uploads/');
            $this->addTree($zip, WRITEPATH . 'uploads/inquiries', 'inquiries/');
        }
        if ($withCode) {
            foreach (self::CODE_DIRS as $dir) {
                $this->addTree($zip, ROOTPATH . $dir, 'site/' . $dir . '/');
            }
            foreach ([...self::CODE_FILES, ...$this->pagerFiles(ROOTPATH)] as $rel) {
                if (is_file(ROOTPATH . $rel)) {
                    $zip->addFile(ROOTPATH . $rel, 'site/' . $rel);
                }
            }
        }

        if ($password !== '') {
            if (! self::canEncrypt()) {
                $zip->close();
                @unlink($file);
                @unlink($sql);
                throw new RuntimeException('no_encryption');
            }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $zip->setEncryptionIndex($i, \ZipArchive::EM_AES_256, $password);
            }
        }

        // The manifest also goes into the (unencrypted) zip comment, so the list can show it without the password.
        $zip->setArchiveComment(json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $zip->close();
        @unlink($sql);

        return $file;
    }

    // ================================================================== saved files

    /**
     * Migration files in writable/migrations/, newest first.
     *
     * @return list<array{name: string, size: int, time: int, manifest: ?array}>
     */
    public function files(): array
    {
        $list = [];
        foreach (glob(self::DIR . '*.zip') ?: [] as $path) {
            $list[] = ['name' => basename($path), 'size' => (int) filesize($path), 'time' => (int) filemtime($path), 'manifest' => $this->manifest($path)];
        }
        usort($list, static fn ($a, $b) => $b['time'] <=> $a['time']);

        return $list;
    }

    /**
     * Full path of a saved file by name, or null (only plain names of .zip files in writable/migrations/).
     */
    public static function path(string $name): ?string
    {
        if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,180}\.zip$/', $name)) {
            return null;
        }
        $path = self::DIR . $name;

        return is_file($path) ? $path : null;
    }

    /**
     * The manifest from the zip comment (readable without the password), or null if this is not a migration file.
     */
    public function manifest(string $path): ?array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::RDONLY) !== true) {
            return null;
        }
        $comment = (string) $zip->getArchiveComment();
        $zip->close();
        $manifest = json_decode($comment, true);

        return is_array($manifest) && ($manifest['app'] ?? '') === 'SurexCore' ? $manifest : null;
    }

    // ================================================================== import (in steps)

    /**
     * Step 1: check the file and the password, unpack it into a work folder. Returns the job id.
     * Nothing on the site is changed yet.
     */
    public function startImport(string $path, string $password = ''): string
    {
        $manifest = $this->manifest($path);
        if ($manifest === null || (int) ($manifest['format'] ?? 0) > self::FORMAT) {
            throw new RuntimeException('not_migration');
        }
        if (version_compare((string) $manifest['version'], config('Cms')->version, '>')) {
            throw new RuntimeException('newer_version');
        }

        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('not_migration');
        }
        if (! empty($manifest['encrypted'])) {
            if ($password === '') {
                $zip->close();
                throw new RuntimeException('password_required');
            }
            $zip->setPassword($password);
        }
        // Reading the small manifest.json proves the password (encrypted entries can't be read without it).
        if ($zip->locateName('database.sql') === false) {
            $zip->close();
            throw new RuntimeException('not_migration');
        }
        if ($zip->getFromName('manifest.json') === false) {
            $zip->close();
            throw new RuntimeException(! empty($manifest['encrypted']) ? 'wrong_password' : 'not_migration');
        }

        @set_time_limit(0);
        $this->ensureDir();
        $job  = bin2hex(random_bytes(16));
        $work = $this->workDir($job);
        mkdir($work, 0700, true);

        $allowed = $this->allowedEntries();
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (str_ends_with($name, '/')) {
                continue;
            }
            // Zip slip guard: plain relative paths inside the known folders only.
            if (str_contains($name, '..') || str_contains($name, '\\') || str_starts_with($name, '/') || ! $allowed($name)) {
                continue;
            }
            $target = $work . '/' . $name;
            if (! is_dir(dirname($target))) {
                mkdir(dirname($target), 0755, true);
            }
            $in = $zip->getStream($name);
            if ($in === false) {
                $zip->close();
                $this->removeTree($work);
                throw new RuntimeException(! empty($manifest['encrypted']) ? 'wrong_password' : 'not_migration');
            }
            $out = fopen($target, 'wb');
            stream_copy_to_stream($in, $out);
            fclose($in);
            fclose($out);
        }
        $zip->close();

        if (! is_file($work . '/database.sql')) {
            $this->removeTree($work);
            throw new RuntimeException('not_migration');
        }

        file_put_contents($work . '/job.json', json_encode(['file' => basename($path), 'manifest' => $manifest]));

        return $job;
    }

    /** The job's manifest (after startImport). */
    public function job(string $job): array
    {
        $file = $this->workDir($job) . '/job.json';
        if (! preg_match('/^[a-f0-9]{32}$/', $job) || ! is_file($file)) {
            throw new RuntimeException('job_missing');
        }

        return json_decode((string) file_get_contents($file), true) ?: throw new RuntimeException('job_missing');
    }

    /**
     * Step: replace the whole database with the one in the file — this site's table prefix, and the
     * old site URL replaced with this site's URL. Returns the number of statements run.
     */
    public function importDatabase(string $job): int
    {
        $info = $this->job($job);

        return $this->runSql(
            $this->workDir($job) . '/database.sql',
            $this->urlReplacements((string) ($info['manifest']['source_url'] ?? ''), base_url()),
        );
    }

    /**
     * Put the database back from a migration file made by this site (the safety backup before an import).
     */
    public function restoreDatabase(string $zipPath): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('restore_failed');
        }
        $tmp = self::DIR . '.restore-' . bin2hex(random_bytes(6)) . '.sql';
        $in  = $zip->getStream('database.sql');
        if ($in === false) {
            $zip->close();
            throw new RuntimeException('restore_failed');
        }
        $out = fopen($tmp, 'wb');
        stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);
        $zip->close();

        try {
            $this->runSql($tmp);
        } finally {
            @unlink($tmp);
        }
    }

    /** Remember the safety backup made before this import (used to roll back the database). */
    public function setJobBackup(string $job, string $path): void
    {
        $info           = $this->job($job);
        $info['backup'] = basename($path);
        file_put_contents($this->workDir($job) . '/job.json', json_encode($info));
    }

    /**
     * Replace all of this site's tables with a database.sql from a migration file.
     */
    private function runSql(string $sql, array $urls = []): int
    {
        $db     = db_connect();
        $prefix = $db->getPrefix();
        @set_time_limit(0);

        $replace = [self::PREFIX_TOKEN => $prefix] + $urls;

        $db->simpleQuery('SET FOREIGN_KEY_CHECKS = 0');
        // Remove every table of this site first: the import is the whole site, nothing old stays behind.
        foreach ($db->listTables(true) as $table) {
            if (str_starts_with($table, $prefix)) {
                $db->simpleQuery('DROP TABLE IF EXISTS ' . $db->escapeIdentifiers($table));
            }
        }

        $in        = fopen($sql, 'rb');
        $statement = '';
        $count     = 0;
        while (($line = fgets($in)) !== false) {
            if ($statement === '' && (str_starts_with($line, '--') || trim($line) === '')) {
                continue;
            }
            $statement .= $line;
            if (str_ends_with(rtrim($line), ';')) {
                $query = strtr(rtrim($statement), $replace);
                if ($db->simpleQuery($query) === false) {
                    fclose($in);
                    $db->simpleQuery('SET FOREIGN_KEY_CHECKS = 1');
                    throw new RuntimeException('database: ' . ($db->error()['message'] ?? 'error'));
                }
                $statement = '';
                $count++;
            }
        }
        fclose($in);
        $db->simpleQuery('SET FOREIGN_KEY_CHECKS = 1');

        return $count;
    }

    /**
     * Step: the uploads and contact attachments replace this site's.
     */
    public function importUploads(string $job): void
    {
        $work = $this->workDir($job);
        $this->job($job);

        foreach (['uploads' => FCPATH . 'uploads', 'inquiries' => WRITEPATH . 'uploads/inquiries'] as $from => $to) {
            if (! is_dir($work . '/' . $from)) {
                continue;
            }
            // Keep the folder's own protection files (.htaccess, index.html), replace everything else.
            $this->emptyDir($to, ['.htaccess', 'index.html']);
            $this->moveContents($work . '/' . $from, $to);
        }
    }

    /**
     * Step: the site's own code replaces this site's (SurexCore itself stays as installed).
     */
    public function importCode(string $job): void
    {
        $site = $this->workDir($job) . '/site';
        $this->job($job);
        if (! is_dir($site)) {
            return;
        }

        foreach (self::CODE_DIRS as $dir) {
            if (is_dir($site . '/' . $dir)) {
                $this->removeTree(ROOTPATH . $dir);
                mkdir(ROOTPATH . $dir, 0755, true);
                $this->moveContents($site . '/' . $dir, ROOTPATH . $dir);
            }
        }
        foreach ([...self::CODE_FILES, ...$this->pagerFiles($site . '/')] as $rel) {
            if (is_file($site . '/' . $rel)) {
                if (! is_dir(dirname(ROOTPATH . $rel))) {
                    mkdir(dirname(ROOTPATH . $rel), 0755, true);
                }
                copy($site . '/' . $rel, ROOTPATH . $rel);
            }
        }
    }

    /**
     * Last step: bring the database up to this SurexCore version, clear caches, remove the work folder.
     */
    public function finishImport(string $job): array
    {
        $info = $this->job($job);

        $installer = service('installer');
        if ($installer->needsUpgrade()) {
            $installer->migrate();
        }

        cache()->clean();
        foreach (glob(WRITEPATH . 'cache/blade/*.bladec') ?: [] as $compiled) {
            @unlink($compiled);
        }
        $this->removeTree($this->workDir($job));

        return $info['manifest'];
    }

    /** Remove the work folder of an import that was stopped. */
    public function abortImport(string $job): void
    {
        if (preg_match('/^[a-f0-9]{32}$/', $job)) {
            $this->removeTree($this->workDir($job));
        }
    }

    // ================================================================== helpers

    private function ensureDir(): void
    {
        if (! is_dir(self::DIR)) {
            mkdir(self::DIR, 0755, true);
        }
        if (! is_file(self::DIR . 'index.html')) {
            file_put_contents(self::DIR . 'index.html', '');
        }
    }

    private function workDir(string $job): string
    {
        return self::DIR . '.import-' . $job;
    }

    /**
     * Old URL → this URL, also in JSON-escaped form ("https:\/\/…") as stored in field values.
     */
    private function urlReplacements(string $from, string $to): array
    {
        $from = rtrim($from, '/');
        $to   = rtrim($to, '/');
        if ($from === '' || $from === $to) {
            return [];
        }
        $json = static fn (string $u) => str_replace('/', '\\\\/', $u);   // as it appears inside the SQL dump

        return [$from => $to, $json($from) => $json($to)];
    }

    /**
     * Which zip entries may be unpacked: the known parts of a migration file, nothing else.
     */
    private function allowedEntries(): \Closure
    {
        $code = [...array_map(static fn ($d) => 'site/' . $d . '/', self::CODE_DIRS)];
        $files = array_map(static fn ($f) => 'site/' . $f, self::CODE_FILES);

        return static function (string $name) use ($code, $files): bool {
            if (in_array($name, ['manifest.json', 'database.sql'], true) || str_starts_with($name, 'uploads/') || str_starts_with($name, 'inquiries/')) {
                return true;
            }
            if (in_array($name, $files, true) || preg_match('#^site/View/system/pager/[A-Za-z0-9_-]+\.php$#', $name)) {
                return true;
            }
            foreach ($code as $dir) {
                if (str_starts_with($name, $dir)) {
                    return true;
                }
            }

            return false;
        };
    }

    /** The site's pagination templates (relative paths), except the admin's bootstrap.php. */
    private function pagerFiles(string $root): array
    {
        $list = [];
        foreach (glob(rtrim($root, '/') . '/' . self::PAGER_DIR . '/*.php') ?: [] as $f) {
            if (basename($f) !== 'bootstrap.php') {
                $list[] = self::PAGER_DIR . '/' . basename($f);
            }
        }

        return $list;
    }

    private function addTree(\ZipArchive $zip, string $root, string $prefix): void
    {
        if (! is_dir($root)) {
            return;
        }
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $f) {
            if (! $f->isFile() || $f->getFilename() === '.DS_Store') {
                continue;
            }
            $local = $prefix . str_replace('\\', '/', substr($f->getPathname(), strlen($root) + 1));
            $zip->addFile($f->getPathname(), $local);
            if (preg_match('/\.(jpe?g|png|gif|webp|zip|pdf|docx|xlsx|pptx|woff2?|mp4)$/i', $local)) {
                $zip->setCompressionName($local, \ZipArchive::CM_STORE);   // compressed already
            }
        }
    }

    private function moveContents(string $from, string $to): void
    {
        if (! is_dir($to)) {
            mkdir($to, 0755, true);
        }
        foreach (scandir($from) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $src = $from . '/' . $item;
            $dst = $to . '/' . $item;
            if (is_dir($src)) {
                $this->moveContents($src, $dst);
                @rmdir($src);
            } elseif (! @rename($src, $dst)) {
                copy($src, $dst);
                @unlink($src);
            }
        }
    }

    private function emptyDir(string $dir, array $keep = []): void
    {
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);

            return;
        }
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..' || in_array($item, $keep, true)) {
                continue;
            }
            is_dir($dir . '/' . $item) ? $this->removeTree($dir . '/' . $item) : @unlink($dir . '/' . $item);
        }
    }

    private function removeTree(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) && ! is_link($path) ? $this->removeTree($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
