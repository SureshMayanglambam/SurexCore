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
            // Runs before AdminController picks the admin language, so choose it here for the message.
            $locale = (string) session('admin_locale');
            service('language')->setLocale(array_key_exists($locale, \App\Admin\AdminController::LOCALES) ? $locale : array_key_first(\App\Admin\AdminController::LOCALES));

            return redirect()->route('admin.dashboard')->with('error', lang('Admin.no_permission'));
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
