<?php

/**
 * Public website routes — write your own here, Laravel style:
 *
 *   $routes->get('about', [About::class, 'index'], ['as' => 'about']);
 *   $routes->get('store/(:segment)', [Store::class, 'show'], ['as' => 'store.show']);
 *
 * Placeholders: (:num) (:segment) (:any) (:alpha) (:alphanum) — passed to the method in order.
 * Build URLs in Blade with url_to('route.name', ...$params).
 *
 * Content from the admin panel (in controllers):
 *   entries('store')->paginate(10)    published entries of a content type
 *   entry('store', $slug)             one entry, or null
 *
 * @var CodeIgniter\Router\RouteCollection $routes
 */

use App\Controller\Api;
use App\Controller\BrandingFile;
use App\Controller\Contact;
use App\Controller\Errors;
use App\Controller\Home;
use App\Controller\Install;
use App\Controller\News;
use App\Controller\Page;
use App\Controller\Seo;

$routes->set404Override(Errors::class . '::notFound');

// Built automatically from these routes and the published entries (App/Config/Sitemap.php)
$routes->get('sitemap.xml', [Seo::class, 'sitemap'], ['as' => 'sitemap']);
$routes->get('robots.txt', [Seo::class, 'robots'], ['as' => 'robots']);

// SVG site logo (Settings → Branding), served with a sandboxing CSP — see App\Controller\BrandingFile
$routes->get('branding/(:segment)', [BrandingFile::class, 'show'], ['as' => 'branding.file']);

// Read-only JSON API: content types listed in cms.apiTypes (.env) — see App\Controller\Api
$routes->group('api', static function ($routes) {
    $routes->get('site', [Api::class, 'site'], ['as' => 'api.site']);
    $routes->get('(:segment)', [Api::class, 'index'], ['as' => 'api.index']);
    $routes->get('(:segment)/(:segment)', [Api::class, 'show'], ['as' => 'api.show']);
    $routes->options('(:any)', [Api::class, 'preflight']);
});

// Installer (only reachable before installation — see App\Filters\InstallCheck)
$routes->get('install', [Install::class, 'index'], ['as' => 'install']);
$routes->post('install', [Install::class, 'store']);

$routes->get('/', [Home::class, 'index'], ['as' => 'home']);

// Pages without their own controller: View/frontend/{url}.blade.php
// $routes->get('about', [Page::class, 'show'], ['as' => 'about']);

// お知らせ
$routes->get('news', [News::class, 'index'], ['as' => 'news']);
$routes->get('news/(:segment)', [News::class, 'detail'], ['as' => 'news.detail']);

// お問い合わせ: 入力 → 確認 → 完了 ("honeypot" adds a hidden anti-spam field; Contact::confirm() checks it)
$routes->get('contact', [Contact::class, 'index'], ['as' => 'contact', 'filter' => 'honeypot']);
$routes->match(['GET', 'POST'], 'contact/confirm', [Contact::class, 'confirm'], ['as' => 'contact.confirm']);
$routes->get('contact/back', [Contact::class, 'back'], ['as' => 'contact.back']);
$routes->post('contact/send', [Contact::class, 'send'], ['as' => 'contact.send']);
$routes->get('contact/thanks', [Contact::class, 'thanks'], ['as' => 'contact.thanks']);
