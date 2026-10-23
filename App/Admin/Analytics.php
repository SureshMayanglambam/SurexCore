<?php

namespace App\Admin;

use App\Libraries\GoogleAnalytics;
use App\Model\SettingModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

/**
 * Settings → Analytics (Admin): connect Google Analytics 4 (property ID + service account key).
 * data(): the numbers for the dashboard card (Admin and WebAdmin), loaded by the page after it renders.
 */
class Analytics extends AdminController
{
    public function index(): string
    {
        $ga = new GoogleAnalytics();

        return $this->render('admin.analytics.index', [
            'propertyId'  => $ga->propertyId(),
            'clientEmail' => $ga->clientEmail(),
            'configured'  => $ga->propertyId() !== '' && is_file(GoogleAnalytics::KEY_FILE),
            'sample'      => $ga->isSample(),
        ]);
    }

    public function update(): RedirectResponse
    {
        // Accept "123456789" or "properties/123456789"; not the G-… measurement ID.
        $property = trim((string) $this->request->getPost('ga4_property_id'));
        $property = preg_replace('#^properties/#', '', $property);

        if ($property !== '' && ! preg_match('/^\d{5,15}$/', $property)) {
            return $this->backWithErrors(['ga4_property_id' => lang('Admin.analytics.bad_property')]);
        }

        $file = $this->request->getFile('key_file');
        if ($file !== null && $file->getError() !== UPLOAD_ERR_NO_FILE) {
            if (! $file->isValid() || $file->getSize() > 20 * 1024) {
                return $this->backWithErrors(['key_file' => lang('Admin.analytics.err_not_service_account')]);
            }
            try {
                GoogleAnalytics::validateKey((string) file_get_contents($file->getTempName()));
            } catch (RuntimeException $e) {
                return $this->backWithErrors(['key_file' => lang('Admin.analytics.err_' . $e->getMessage())]);
            }

            $dir = dirname(GoogleAnalytics::KEY_FILE);
            if (! is_dir($dir) && ! mkdir($dir, 0700, true)) {
                return $this->backWithErrors(['key_file' => lang('Admin.analytics.cannot_write')]);
            }
            @file_put_contents($dir . DIRECTORY_SEPARATOR . 'index.html', '');
            if (! copy($file->getTempName(), GoogleAnalytics::KEY_FILE)) {
                return $this->backWithErrors(['key_file' => lang('Admin.analytics.cannot_write')]);
            }
            @chmod(GoogleAnalytics::KEY_FILE, 0600);
            log_activity('settings.analytics', 'Google Analyticsのサービスアカウントキーを登録しました');
        }

        model(SettingModel::class)->put('ga4_property_id', $property);
        model(SettingModel::class)->put('ga4_sample', $this->request->getPost('ga4_sample') === '1' ? '1' : '0');
        (new GoogleAnalytics())->clearCache();
        log_activity('settings.analytics', 'Google AnalyticsのプロパティIDを設定しました（' . ($property ?: '未設定') . '）');

        return redirect()->route('admin.analytics')->with('success', lang('Admin.analytics.saved'));
    }

    public function removeKey(): RedirectResponse
    {
        if (is_file(GoogleAnalytics::KEY_FILE)) {
            unlink(GoogleAnalytics::KEY_FILE);
        }
        (new GoogleAnalytics())->clearCache();
        log_activity('settings.analytics', 'Google Analyticsのサービスアカウントキーを削除しました');

        return redirect()->route('admin.analytics')->with('success', lang('Admin.analytics.key_removed'));
    }

    /**
     * "Test connection": fetch a fresh 7-day report and say what went wrong, if anything.
     */
    public function test(): RedirectResponse
    {
        try {
            $report = (new GoogleAnalytics())->report(7, true);
        } catch (RuntimeException $e) {
            return redirect()->route('admin.analytics')->with('error', $this->message($e, true));
        }

        return redirect()->route('admin.analytics')
            ->with('success', lang('Admin.analytics.test_ok', [number_format((int) $report['current']['activeUsers'])]));
    }

    /**
     * JSON for the dashboard card. WebAdmins get a short message instead of setup details.
     */
    public function data(): ResponseInterface
    {
        $ga      = new GoogleAnalytics();
        $isAdmin = current_user()->role === 'admin';

        if (! $ga->isConfigured()) {
            return $this->response->setJSON(['configured' => false]);
        }

        try {
            $report = $ga->report((int) $this->request->getGet('days'));
            try {
                $report['active_now'] = $ga->activeNow();
            } catch (RuntimeException) {
                $report['active_now'] = null;   // realtime is a bonus: the report still shows
            }
        } catch (RuntimeException $e) {
            return $this->response->setJSON(['configured' => true, 'error' => $this->message($e, $isAdmin)]);
        }

        return $this->response->setJSON(['configured' => true, 'report' => $report, 'sample' => ! empty($report['sample'])]);
    }

    private function message(RuntimeException $e, bool $detailed): string
    {
        if (! $detailed) {
            return lang('Admin.analytics.err_generic');
        }

        [$code, $detail] = array_pad(explode(': ', $e->getMessage(), 2), 2, '');
        $line            = lang('Admin.analytics.err_' . $code, [(new GoogleAnalytics())->clientEmail() ?? '']);

        return str_starts_with($line, 'Admin.') ? lang('Admin.analytics.err_google', [$detail ?: $code]) : ($detail !== '' ? $line . ' ' . $detail : $line);
    }
}
