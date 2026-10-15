<?php

namespace App\Libraries;

use App\Model\UserModel;

/**
 * Failed-login protection per login ID, on top of the per-IP throttle in App\Admin\Auth:
 * - after cms.loginAlertAfter failures the admins get an email (who, from where, when);
 * - after cms.loginLockAfter failures the login ID is locked for cms.loginLockMinutes, from any IP;
 * - after cms.ipBlockAfter failures from one IP, that IP is blocked from the admin panel until it is
 *   unblocked (App\Libraries\IpBlocklist).
 *
 * Failures are counted for any login ID, existing or not, so a lock never reveals which accounts exist.
 * The count resets after a successful login, or once no failure happened for cms.loginLockMinutes.
 */
class LoginGuard
{
    /** At most this many alert emails per hour, so an attacker can't turn the alerts into mail spam. */
    private const MAX_MAILS_PER_HOUR = 10;

    private int $alertAfter;
    private int $lockAfter;
    private int $ipBlockAfter;
    private int $window;

    public function __construct()
    {
        $cms              = config('Cms');
        $this->alertAfter = max(0, $cms->loginAlertAfter);
        $this->lockAfter  = max(0, $cms->loginLockAfter);
        $this->ipBlockAfter = max(0, $cms->ipBlockAfter);
        $this->window     = max(1, $cms->loginLockMinutes) * MINUTE;
    }

    /**
     * Seconds until the login ID can be tried again, 0 when it isn't locked.
     */
    public function lockedFor(string $login): int
    {
        $state = $this->state($login);

        if ($this->lockAfter === 0 || $state['count'] < $this->lockAfter) {
            return 0;
        }

        return max(0, $state['last'] + $this->window - time());
    }

    public function failed(string $login, string $ip, string $userAgent): void
    {
        $state = $this->state($login);
        $state = ['count' => $state['count'] + 1, 'last' => time()];
        cache()->save($this->key($login), $state, $this->window);

        // Per IP, whatever login IDs it tries
        $ipKey   = 'login_guard_ip_' . sha1($ip);
        $ipCount = (int) cache($ipKey) + 1;
        cache()->save($ipKey, $ipCount, $this->window);

        if ($this->ipBlockAfter > 0 && $ipCount >= $this->ipBlockAfter
            && (new IpBlocklist())->block($ip, $ipCount . ' failed logins, last as "' . mb_substr($login, 0, 60) . '"')) {
            log_activity('auth.ip_blocked', 'IPアドレス「' . $ip . '」を管理画面からブロックしました（ログイン失敗' . $ipCount . '回）');
            // One email for this attempt: the block notice already names the login ID that was tried.
            $this->notify('block', $login, $ip, $userAgent, $ipCount);

            return;
        }

        if ($this->alertAfter > 0 && $state['count'] === $this->alertAfter) {
            $this->notify('alert', $login, $ip, $userAgent, $state['count']);
        }
        if ($this->lockAfter > 0 && $state['count'] === $this->lockAfter) {
            log_activity('auth.locked', 'ログインIDをロックしました（「' . mb_substr($login, 0, 100) . '」・' . $state['count'] . '回失敗）');
            $this->notify('lock', $login, $ip, $userAgent, $state['count']);
        }
    }

    public function succeeded(string $login, string $ip): void
    {
        cache()->delete($this->key($login));
        cache()->delete('login_guard_ip_' . sha1($ip));
    }

    /**
     * @return array{count: int, last: int}
     */
    private function state(string $login): array
    {
        $state = cache($this->key($login));

        return is_array($state) ? $state + ['count' => 0, 'last' => 0] : ['count' => 0, 'last' => 0];
    }

    private function key(string $login): string
    {
        // Same normalisation as UserModel::attempt(); hashed, so the cache key is always valid.
        return 'login_guard_' . sha1(strtolower(trim($login)));
    }

    /**
     * Email the admins (cms.adminEmail, or every active Administrator). In English and Japanese:
     * the request comes from an anonymous visitor, so the admins' language is unknown.
     */
    private function notify(string $kind, string $login, string $ip, string $userAgent, int $count): void
    {
        $to = $this->recipients();
        if ($to === []) {
            return;
        }

        $hourKey = 'login_guard_mails_' . date('YmdH');
        $sent    = (int) cache($hourKey);
        if ($sent >= self::MAX_MAILS_PER_HOUR) {
            return;
        }
        cache()->save($hourKey, $sent + 1, HOUR);

        $site    = setting('site_name', config('Cms')->appName);
        $minutes = (int) ($this->window / MINUTE);
        $subject = "login_{$kind}_subject";
        $intro   = "login_{$kind}_intro";
        $advice  = $kind === 'block' ? 'login_block_advice' : 'login_advice';
        $link    = $kind === 'block' ? url_to('admin.security') : url_to('admin.activity');
        $rows    = [
            'login_lbl_login'   => mb_substr($login, 0, 100) ?: '—',
            'login_lbl_ip'      => $ip,
            'login_lbl_time'    => date('Y-m-d H:i:s'),
            'login_lbl_browser' => mb_substr($userAgent, 0, 200) ?: '—',
        ];

        $body = '';
        foreach (['en', 'ja'] as $i => $locale) {
            $body .= ($i ? '<hr style="border:0;border-top:1px solid #ddd;margin:24px 0">' : '')
                . '<p>' . esc(lang("Admin.mail.{$intro}", [$count, $minutes], $locale)) . '</p><table cellpadding="4">';
            foreach ($rows as $label => $value) {
                $body .= '<tr><th align="left">' . esc(lang("Admin.mail.{$label}", [], $locale)) . '</th><td>' . esc($value) . '</td></tr>';
            }
            $body .= '</table><p>' . esc(lang("Admin.mail.{$advice}", [], $locale)) . '<br>'
                . '<a href="' . esc($link, 'attr') . '">' . esc($link) . '</a></p>';
        }

        send_mail(
            $to,
            lang("Admin.mail.{$subject}", [$site], 'en') . ' / ' . lang("Admin.mail.{$subject}", [$site], 'ja'),
            $body,
        );
    }

    /**
     * @return list<string>
     */
    private function recipients(): array
    {
        $email = trim(config('Cms')->adminEmail);
        if ($email !== '') {
            return [$email];
        }

        $admins = model(UserModel::class)->where('role', 'admin')->where('status', 'active')
            ->where('email IS NOT NULL')->where('email !=', '')->findColumn('email');

        return array_values(array_unique($admins ?? []));
    }
}
