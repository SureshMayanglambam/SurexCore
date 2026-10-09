<?php

namespace App\Admin;

use App\Model\ActivityLogModel;
use App\Model\ContentTypeModel;
use App\Model\EntryModel;
use App\Model\UserModel;
use CodeIgniter\CodeIgniter;

class Dashboard extends AdminController
{
    /** Months shown in the publishing chart. */
    private const MONTHS = 6;

    public function index(): string
    {
        $now        = date('Y-m-d H:i:s');
        $monthStart = date('Y-m-01 00:00:00');
        $lastMonth  = date('Y-m-01 00:00:00', strtotime('first day of last month'));
        $chartStart = date('Y-m-01 00:00:00', strtotime('first day of -' . (self::MONTHS - 1) . ' months'));

        // Month labels for the chart: [2026-04 => 'Apr', ...]
        $months = [];
        for ($i = self::MONTHS - 1; $i >= 0; $i--) {
            $ts                        = strtotime("first day of -{$i} months");
            $months[date('Y-m', $ts)] = date(lang('Admin.dash.month_fmt'), $ts);
        }

        $types  = [];
        $recent = [];
        $totals = ['published' => 0, 'scheduled' => 0, 'draft' => 0, 'thisMonth' => 0, 'lastMonth' => 0];

        // Each content type has its own table: count per table, then merge.
        foreach (model(ContentTypeModel::class)->allBySlug() as $slug => $type) {
            $stats = [
                'published' => EntryModel::for($type)->published()->countAllResults(),
                'scheduled' => EntryModel::for($type)->where('status', 'published')->where('published_at >', $now)->countAllResults(),
                'draft'     => EntryModel::for($type)->where('status', 'draft')->countAllResults(),
                'thisMonth' => EntryModel::for($type)->where('status', 'published')->where('published_at >=', $monthStart)->where('published_at <=', $now)->countAllResults(),
                'lastMonth' => EntryModel::for($type)->where('status', 'published')->where('published_at >=', $lastMonth)->where('published_at <', $monthStart)->countAllResults(),
                'monthly'   => array_fill_keys(array_keys($months), 0),
            ];

            $rows = EntryModel::for($type)->asArray()
                ->select("DATE_FORMAT(published_at, '%Y-%m') AS ym, COUNT(*) AS total", false)
                ->where('status', 'published')->where('published_at >=', $chartStart)->where('published_at <=', $now)
                ->groupBy('ym')->findAll();
            foreach ($rows as $row) {
                if (isset($stats['monthly'][$row['ym']])) {
                    $stats['monthly'][$row['ym']] = (int) $row['total'];
                }
            }

            foreach (array_keys($totals) as $key) {
                $totals[$key] += $stats[$key];
            }

            $types[$slug] = ['type' => $type, 'stats' => $stats];

            foreach (EntryModel::for($type)->withAuthor()->orderBy('updated_at', 'DESC')->findAll(6) as $entry) {
                $recent[] = ['type' => $type, 'entry' => $entry];
            }
        }

        usort($recent, static fn ($a, $b) => strcmp((string) $b['entry']->updated_at, (string) $a['entry']->updated_at));

        $data = [
            'months'     => $months,
            'types'      => $types,
            'totals'     => $totals,
            'recent'     => array_slice($recent, 0, 6),
            'hour'       => (int) date('G'),
            // Longest bar in "Content types" = the type with the most entries
            'maxEntries' => max([1, ...array_map(static fn ($t) => $t['stats']['published'] + $t['stats']['draft'] + $t['stats']['scheduled'], array_values($types))]),
        ];

        if (current_user()->role === 'admin') {
            $users = model(UserModel::class);

            $data['activity'] = model(ActivityLogModel::class)->orderBy('id', 'DESC')->findAll(7);
            $data['people']   = [
                'total'    => $users->countAllResults(),
                'admins'   => $users->where('role', 'admin')->where('status', 'active')->countAllResults(),
                'webadmin' => $users->where('role', 'webadmin')->where('status', 'active')->countAllResults(),
            ];
            $data['system'] = $this->systemStatus();
            $data['disk']   = $this->diskUsage($this->request->getGet('refresh_disk') === '1');
        }

        return $this->render('admin.dashboard', $data);
    }

    /**
     * Disk space used by this site: files + database, in bytes.
     * Walking every file takes a moment, so the result is cached for an hour (再計算 link refreshes it).
     */
    private function diskUsage(bool $refresh = false): array
    {
        if (! $refresh && is_array($cached = cache('disk_usage'))) {
            return $cached;
        }

        $files    = $this->folderSize(ROOTPATH);
        $uploads  = $this->folderSize(FCPATH . 'uploads');
        $writable = $this->folderSize(WRITEPATH);
        $database = $this->databaseSize();

        $usage = [
            'total'      => $files + $database,
            'uploads'    => $uploads,
            'database'   => $database,
            'writable'   => $writable,
            'program'    => max(0, $files - $uploads - $writable),
            'checked_at' => date('Y-m-d H:i'),
        ];
        cache()->save('disk_usage', $usage, HOUR);

        return $usage;
    }

    /**
     * Total size of the files in a folder (symlinked folders and unreadable files are skipped).
     */
    private function folderSize(string $path): int
    {
        if (! is_dir($path)) {
            return 0;
        }

        $size = 0;
        try {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY,
                \RecursiveIteratorIterator::CATCH_GET_CHILD
            );
            foreach ($files as $file) {
                if ($file->isFile() && ! $file->isLink()) {
                    $size += $file->getSize();
                }
            }
        } catch (\Throwable) {
            // Unreadable folder: count what was reached.
        }

        return $size;
    }

    /**
     * Size of this site's tables (data + indexes). Only tables with this site's prefix,
     * in case the database is shared with another site.
     */
    private function databaseSize(): int
    {
        try {
            $db  = db_connect();
            $row = $db->query(
                'SELECT COALESCE(SUM(data_length + index_length), 0) AS size FROM information_schema.tables'
                . ' WHERE table_schema = DATABASE() AND table_name LIKE ?',
                [str_replace('_', '\\_', $db->DBPrefix) . '%']
            )->getRow();

            return (int) ($row->size ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Quick health overview for Admins.
     */
    private function systemStatus(): array
    {
        $mail = config('Email');

        return [
            ['CMS', \Config\Cms::NAME . ' ' . config('Cms')->version, true],
            ['CodeIgniter', CodeIgniter::CI_VERSION, true],
            ['PHP', PHP_VERSION, version_compare(PHP_VERSION, '8.2', '>=')],
            [lang('Admin.sys.database'), 'MySQL ' . db_connect()->getVersion(), true],
            [lang('Admin.sys.mode'), ['production' => lang('Admin.sys.production'), 'development' => lang('Admin.sys.development'), 'testing' => lang('Admin.sys.testing')][ENVIRONMENT] ?? ENVIRONMENT, ENVIRONMENT === 'production' ? true : null],
            [lang('Admin.sys.mail'), $mail->SMTPHost !== '' ? $mail->SMTPHost : lang('Admin.sys.not_set'), $mail->protocol !== 'smtp' || $mail->SMTPHost !== ''],
            [lang('Admin.sys.maintenance'), setting('maintenance_mode') === '1' ? lang('Admin.sys.on') : lang('Admin.sys.off'), setting('maintenance_mode') === '1' ? null : true],
            [lang('Admin.sys.upload_dir'), is_writable(FCPATH . 'uploads') ? lang('Admin.sys.writable') : lang('Admin.sys.not_writable'), is_writable(FCPATH . 'uploads')],
        ];
    }
}
