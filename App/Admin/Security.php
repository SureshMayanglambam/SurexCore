<?php

namespace App\Admin;

use App\Libraries\IpBlocklist;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Settings → Security (Admin only): IPs blocked from the admin panel after failed logins,
 * and the allow list of IPs that are never blocked. Both are text files in writable/security/.
 */
class Security extends AdminController
{
    public function index(): string
    {
        $list = new IpBlocklist();
        $cms  = config('Cms');

        return $this->render('admin.security.index', [
            'blocked'   => $list->blocked(),
            'allowed'   => $list->allowed(),
            'myIp'      => $this->request->getIPAddress(),
            'myAllowed' => $list->isAllowed($this->request->getIPAddress()),
            'cms'       => $cms,
            'files'     => [
                'blocked' => 'writable/security/' . basename(IpBlocklist::BLOCKED),
                'allowed' => 'writable/security/' . basename(IpBlocklist::ALLOWED),
            ],
        ]);
    }

    public function unblock(): RedirectResponse
    {
        $ip = IpBlocklist::normalize((string) $this->request->getPost('ip'));

        if ($ip === null || ! (new IpBlocklist())->unblock($ip)) {
            return redirect()->route('admin.security')->with('error', lang('Admin.security.save_failed'));
        }

        log_activity('settings.ip_unblocked', 'IPアドレス「' . $ip . '」のブロックを解除しました');

        return redirect()->route('admin.security')->with('success', lang('Admin.security.unblocked', [$ip]));
    }

    /**
     * Allow list from the textarea: one IP per line, optional "# note".
     */
    public function updateAllowed(): RedirectResponse
    {
        $list    = [];
        $invalid = [];

        foreach (preg_split('/\R/', (string) $this->request->getPost('allowed')) as $line) {
            [$value, $note] = array_pad(explode('#', $line, 2), 2, '');
            if (trim($value) === '') {
                continue;
            }
            $ip = IpBlocklist::normalize($value);
            $ip === null ? $invalid[] = trim($value) : $list[$ip] = mb_substr(trim($note), 0, 100);
        }

        if ($invalid !== []) {
            return $this->backWithErrors(['allowed' => lang('Admin.security.invalid_ips', [implode(', ', array_slice($invalid, 0, 5))])]);
        }
        if (! (new IpBlocklist())->setAllowed($list)) {
            return redirect()->route('admin.security')->with('error', lang('Admin.security.save_failed'));
        }

        log_activity('settings.ip_allowlist', '許可IPリストを更新しました（' . count($list) . '件）');

        return redirect()->route('admin.security')->with('success', lang('Admin.security.allowed_saved'));
    }
}
