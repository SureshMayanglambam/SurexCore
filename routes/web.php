<?php

/**
 * Public website routes — write your own here, Laravel style:
 *
 *   $routes->get('about', [About::class, 'index'], ['as' => 'about']);
 *   $routes->get('store/(:segment)', [Store::class, 'show'], ['as' => 'store.show']);
 *
 * Placeholders: (:num) (:segment) (:any) (:alpha) (:alphanum) — passed to the method in order.
 * Build URLs in Blade with url_to('route.name', ...$params). List all routes: php spark routes
 *
 * Every GET route without placeholders is added to sitemap.xml automatically (App/Config/Sitemap.php).
 *
 * @var CodeIgniter\Router\RouteCollection $routes
 */

use App\Controller\Contact;
use App\Controller\Errors;
use App\Controller\Home;
use App\Controller\Install;
use App\Controller\News;
use App\Controller\Seo;

$routes->set404Override(Errors::class . '::notFound');

// Built automatically from these routes and the published entries (App/Config/Sitemap.php)
$routes->get('sitemap.xml', [Seo::class, 'sitemap'], ['as' => 'sitemap']);
$routes->get('robots.txt', [Seo::class, 'robots'], ['as' => 'robots']);

// Installer (only reachable before installation — see App\Filters\InstallCheck)
$routes->get('install', [Install::class, 'index'], ['as' => 'install']);
$routes->post('install', [Install::class, 'store']);

// Top page (the SurexCore welcome page — replace View/frontend/index.blade.php)
$routes->get('/', [Home::class, 'index'], ['as' => 'home']);

// SAMPLE: お知らせ — list + detail (App/Controller/News.php, App/Model/NewsModel.php, View/frontend/news/)
$routes->get('news', [News::class, 'index'], ['as' => 'news']);
$routes->get('news/(:segment)', [News::class, 'detail'], ['as' => 'news.detail']);

// お問い合わせ: 入力 → 確認 → 完了 ("honeypot" adds a hidden anti-spam field; Contact::confirm() checks it)
$routes->get('contact', [Contact::class, 'index'], ['as' => 'contact', 'filter' => 'honeypot']);
$routes->match(['GET', 'POST'], 'contact/confirm', [Contact::class, 'confirm'], ['as' => 'contact.confirm']);
$routes->get('contact/back', [Contact::class, 'back'], ['as' => 'contact.back']);
$routes->post('contact/send', [Contact::class, 'send'], ['as' => 'contact.send']);
$routes->get('contact/thanks', [Contact::class, 'thanks'], ['as' => 'contact.thanks']);
