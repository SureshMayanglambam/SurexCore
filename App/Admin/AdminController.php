<?php

namespace App\Admin;

use App\Controller\BaseController;
use App\Model\ContentTypeModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Base for admin panel screens. Views live in View/admin/.
 */
abstract class AdminController extends BaseController
{
    /** Languages the admin panel is available in. First is the default. */
    public const LOCALES = ['en' => 'English', 'ja' => '日本語'];

    protected string $locale = 'ja';

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        // Admin UI language: a per-browser choice (top-bar switch), remembered in the session.
        $locale = (string) (session('admin_locale') ?? '');
        if (! array_key_exists($locale, self::LOCALES)) {
            $locale = array_key_first(self::LOCALES);
        }

        $this->locale = $locale;
        $request->setLocale($locale);
        service('language')->setLocale($locale);
    }

    protected function sharedData(): array
    {
        $user = current_user();

        return [
            'user'         => $user,
            'isAdmin'      => ($user->role ?? null) === 'admin',
            'siteName'     => setting('site_name', 'SurexCore'),
            'siteLogo'     => $user ? setting('site_logo', '') : '',
            'contentTypes' => $user ? model(ContentTypeModel::class)->allBySlug() : [],
            // Role display labels follow the admin language; the keys (admin/webadmin) stay.
            'roles'        => ['admin' => lang('Admin.role_admin'), 'webadmin' => lang('Admin.role_webadmin')],
            'notice'       => service('installer')->notice,
            'version'      => config('Cms')->version,
            'locale'       => $this->locale,
            'locales'      => self::LOCALES,
        ];
    }

    /**
     * Redirect back to the form with validation errors and the submitted input.
     */
    protected function backWithErrors(array $errors): RedirectResponse
    {
        return redirect()->back()->withInput()->with('errors', $errors);
    }
}
