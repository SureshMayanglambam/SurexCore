<?php

/**
 * SurexCore's own public routes — not yours to edit (they come with every SurexCore update).
 * Loaded first, so they always win; your site's routes are in routes/web.php.
 *
 * @var CodeIgniter\Router\RouteCollection $routes
 */

use App\Controller\Api;
use App\Controller\BrandingFile;
use App\Controller\Errors;
use App\Controller\Install;
use App\Controller\Seo;

$routes->set404Override(Errors::class . '::notFound');

// Built automatically from the routes and the published entries (App/Config/Sitemap.php)
$routes->get('sitemap.xml', [Seo::class, 'sitemap'], ['as' => 'sitemap']);
$routes->get('robots.txt', [Seo::class, 'robots'], ['as' => 'robots']);

// SVG site logo (Settings → Branding), served with a sandboxing CSP — see App\Controller\BrandingFile
$routes->get('branding/(:segment)', [BrandingFile::class, 'show'], ['as' => 'branding.file']);

// Read-only JSON API ("Expose REST API" per content type) — see App\Controller\Api
$routes->group('api', static function ($routes) {
    $routes->get('site', [Api::class, 'site'], ['as' => 'api.site']);
    $routes->get('(:segment)', [Api::class, 'index'], ['as' => 'api.index']);
    $routes->get('(:segment)/(:segment)', [Api::class, 'show'], ['as' => 'api.show']);
    $routes->options('(:any)', [Api::class, 'preflight']);
});

// Installer (only reachable before installation — see App\Filters\InstallCheck)
$routes->get('install', [Install::class, 'index'], ['as' => 'install']);
$routes->post('install', [Install::class, 'store']);
