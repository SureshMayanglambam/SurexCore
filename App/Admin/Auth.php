<?php

namespace App\Admin;

use App\Model\UserModel;
use CodeIgniter\HTTP\RedirectResponse;

class Auth extends AdminController
{
    /**
     * Failed-login allowance per IP: this many attempts per minute.
     */
    private const MAX_ATTEMPTS = 5;

    public function login(): RedirectResponse|string
    {
        if (current_user() !== null) {
            return redirect()->route('admin.dashboard');
        }

        return $this->render('admin.login');
    }

    public function attempt(): RedirectResponse
    {
        $throttler = service('throttler');
        $key       = 'login-' . md5($this->request->getIPAddress());

        if (! $throttler->check($key, self::MAX_ATTEMPTS, MINUTE)) {
            return redirect()->back()->with('error', sprintf(
                'ログインの試行回数が多すぎます。%d秒後に再度お試しください。',
                max(1, $throttler->getTokenTime()),
            ));
        }

        // Login ID or email
        $login    = trim((string) $this->request->getPost('login'));
        $password = (string) $this->request->getPost('password');
        $users    = model(UserModel::class);
        $user     = $login !== '' && $password !== '' ? $users->attempt($login, $password) : null;

        if ($user === null) {
            log_activity('auth.failed', 'ログインに失敗しました（「' . mb_substr($login, 0, 100) . '」）');

            return redirect()->back()->with('error', 'ログインID／メールアドレスまたはパスワードが正しくありません。')->with('old_login', $login);
        }

        // New session ID on login prevents session fixation.
        session()->regenerate(true);
        session()->set('user_id', $user->id);
        $users->update($user->id, ['last_login_at' => date('Y-m-d H:i:s')]);
        log_activity('auth.login', 'ログインしました', 'user', (int) $user->id, $user);

        $target = session()->get('redirect_after_login');
        session()->remove('redirect_after_login');

        // Only follow internal admin URLs (no open redirects).
        if (! is_string($target) || ! str_starts_with($target, site_url(admin_path()))) {
            $target = url_to('admin.dashboard');
        }

        return redirect()->to($target);
    }

    public function logout(): RedirectResponse
    {
        log_activity('auth.logout', 'ログアウトしました', 'user', (int) current_user()->id);

        session()->remove('user_id');
        session()->regenerate(true);

        return redirect()->route('admin.login')->with('success', 'ログアウトしました。');
    }
}
