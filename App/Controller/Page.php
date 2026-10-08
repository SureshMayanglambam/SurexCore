<?php

namespace App\Controller;

use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Pages that are only a Blade view (no data). The URL picks the view:
 *
 *   $routes->get('about', [Page::class, 'show'], ['as' => 'about']);
 *       → View/frontend/about.blade.php  (or View/frontend/about/index.blade.php)
 *   $routes->get('company/access', [Page::class, 'show'], ['as' => 'company.access']);
 *       → View/frontend/company/access.blade.php
 */
class Page extends FrontController
{
    public function show(): string
    {
        $path = trim(uri_string(), '/');

        if (! preg_match('#^[a-z0-9_-]+(/[a-z0-9_-]+)*$#', $path)) {
            throw PageNotFoundException::forPageNotFound();
        }

        foreach ([$path, $path . '/index'] as $file) {
            if (is_file(ROOTPATH . 'View/frontend/' . $file . '.blade.php')) {
                return $this->render('frontend.' . str_replace('/', '.', $file));
            }
        }

        throw PageNotFoundException::forPageNotFound();
    }
}
