<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Requires a logged-in, active user. Optional role argument: 'auth:admin'.
 */
class AdminAuth implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $user = current_user();

        if ($user === null) {
            session()->remove('user_id');
            session()->set('redirect_after_login', current_url());

            return redirect()->route('admin.login');
        }

        if (! empty($arguments) && ! in_array($user->role, $arguments, true)) {
            return redirect()->route('admin.dashboard')->with('error', 'このページにアクセスする権限がありません。');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
