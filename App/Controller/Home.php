<?php

namespace App\Controller;

use CodeIgniter\CodeIgniter;
use Config\Cms;

/**
 * Top page. This starter shows the SurexCore welcome page — replace it with your site's top page
 * (View/frontend/index.blade.php).
 */
class Home extends FrontController
{
    public function index(): string
    {
        return $this->render('frontend.index', [
            'cms' => [
                'version'   => config('Cms')->version,
                'developer' => Cms::DEVELOPER,
                'framework' => 'CodeIgniter ' . CodeIgniter::CI_VERSION,
                'php'       => PHP_VERSION,
            ],
            'adminUrl' => url_to('admin.login'),
        ]);
    }
}
