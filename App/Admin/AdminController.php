<?php

namespace App\Admin;

use App\Controller\BaseController;
use App\Model\ContentTypeModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Base for admin panel screens. Views live in View/admin/.
 */
abstract class AdminController extends BaseController
{
    protected function sharedData(): array
    {
        $user = current_user();

        return [
            'user'         => $user,
            'isAdmin'      => ($user->role ?? null) === 'admin',
            'siteName'     => setting('site_name', 'SurexCore'),
            'siteLogo'     => $user ? setting('site_logo', '') : '',
            'contentTypes' => $user ? model(ContentTypeModel::class)->allBySlug() : [],
            'roles'        => config('Cms')->roles,
            'notice'       => service('installer')->notice,
            'version'      => config('Cms')->version,
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
