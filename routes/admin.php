<?php

/**
 * Admin panel routes: /{cms.adminPath}/… ("admin" unless .env says otherwise).
 *
 * Pending database migrations run automatically on any admin request (dbupgrade),
 * including the login page, so a deploy that changes the users table can't lock anyone out.
 *
 * @var CodeIgniter\Router\RouteCollection $routes
 */

use App\Admin\ActivityLog;
use App\Admin\Auth;
use App\Admin\Backup;
use App\Admin\Branding;
use App\Admin\ContentTypes;
use App\Admin\Dashboard;
use App\Admin\Entries;
use App\Admin\Inquiries;
use App\Admin\Media;
use App\Admin\Profile;
use App\Admin\Settings;
use App\Admin\Users;

$routes->group(config('Cms')->adminPath, ['filter' => 'dbupgrade'], static function ($routes) {
    $routes->get('login', [Auth::class, 'login'], ['as' => 'admin.login']);
    $routes->post('login', [Auth::class, 'attempt'], ['as' => 'admin.login.attempt']);

    // Admin + WebAdmin
    $routes->group('', ['filter' => 'auth'], static function ($routes) {
        $routes->post('logout', [Auth::class, 'logout'], ['as' => 'admin.logout']);
        $routes->get('/', [Dashboard::class, 'index'], ['as' => 'admin.dashboard']);

        $routes->get('profile', [Profile::class, 'edit'], ['as' => 'admin.profile']);
        $routes->put('profile', [Profile::class, 'update'], ['as' => 'admin.profile.update']);

        // Settings → Branding: site logo (Admin and WebAdmin)
        $routes->get('branding', [Branding::class, 'index'], ['as' => 'admin.branding']);
        $routes->post('branding', [Branding::class, 'update'], ['as' => 'admin.branding.update']);
        $routes->delete('branding', [Branding::class, 'delete'], ['as' => 'admin.branding.delete']);

        // Image/file uploads for custom fields and CKEditor
        $routes->post('upload', [Media::class, 'upload'], ['as' => 'admin.upload']);

        // お問い合わせ: saved form submissions (only while 一般設定 → お問い合わせを保存する is on)
        $routes->get('inquiries', [Inquiries::class, 'index'], ['as' => 'admin.inquiries']);
        $routes->get('inquiries/(:num)', [Inquiries::class, 'show'], ['as' => 'admin.inquiries.show']);
        $routes->post('inquiries/(:num)/unread', [Inquiries::class, 'unread'], ['as' => 'admin.inquiries.unread']);
        $routes->get('inquiries/(:num)/attachment', [Inquiries::class, 'attachment'], ['as' => 'admin.inquiries.attachment']);
        $routes->delete('inquiries/(:num)', [Inquiries::class, 'delete'], ['as' => 'admin.inquiries.delete']);

        // メディア: the media library (and its picker in entry forms)
        $routes->get('media', [Media::class, 'index'], ['as' => 'admin.media']);
        $routes->get('media/list', [Media::class, 'list'], ['as' => 'admin.media.list']);
        $routes->get('media/(:num)', [Media::class, 'show'], ['as' => 'admin.media.show']);
        $routes->delete('media/(:num)', [Media::class, 'delete'], ['as' => 'admin.media.delete']);

        // Entries of the content types created under Settings → Content Types (e.g. /admin/content/news)
        $routes->get('content/(:segment)', [Entries::class, 'index'], ['as' => 'admin.entries']);
        $routes->get('content/(:segment)/create', [Entries::class, 'create'], ['as' => 'admin.entries.create']);
        $routes->post('content/(:segment)', [Entries::class, 'store'], ['as' => 'admin.entries.store']);
        $routes->get('content/(:segment)/(:num)/edit', [Entries::class, 'edit'], ['as' => 'admin.entries.edit']);
        $routes->put('content/(:segment)/(:num)', [Entries::class, 'update'], ['as' => 'admin.entries.update']);
        $routes->delete('content/(:segment)/(:num)', [Entries::class, 'delete'], ['as' => 'admin.entries.delete']);
        $routes->post('content/(:segment)/(:num)/duplicate', [Entries::class, 'duplicate'], ['as' => 'admin.entries.duplicate']);
        // ゴミ箱: restore / delete for good / empty
        $routes->post('content/(:segment)/(:num)/restore', [Entries::class, 'restore'], ['as' => 'admin.entries.restore']);
        $routes->delete('content/(:segment)/(:num)/purge', [Entries::class, 'purge'], ['as' => 'admin.entries.purge']);
        $routes->delete('content/(:segment)/trash', [Entries::class, 'emptyTrash'], ['as' => 'admin.entries.emptyTrash']);
        // Preview of the unsaved form in the frontend template (POST, or PUT from the edit form)
        $routes->match(['POST', 'PUT'], 'content/(:segment)/preview', [Entries::class, 'preview'], ['as' => 'admin.entries.preview']);
        $routes->match(['POST', 'PUT'], 'content/(:segment)/(:num)/preview', [Entries::class, 'preview'], ['as' => 'admin.entries.preview.edit']);
    });

    // Admin only: the Settings menu
    $routes->group('settings', ['filter' => 'auth:admin'], static function ($routes) {
        $routes->get('/', [Settings::class, 'index'], ['as' => 'admin.settings']);
        $routes->put('/', [Settings::class, 'update'], ['as' => 'admin.settings.update']);
        $routes->post('test-email', [Settings::class, 'testEmail'], ['as' => 'admin.settings.testEmail']);

        $routes->get('users', [Users::class, 'index'], ['as' => 'admin.users']);
        $routes->get('users/create', [Users::class, 'create'], ['as' => 'admin.users.create']);
        $routes->post('users', [Users::class, 'store'], ['as' => 'admin.users.store']);
        $routes->get('users/(:num)/edit', [Users::class, 'edit'], ['as' => 'admin.users.edit']);
        $routes->put('users/(:num)', [Users::class, 'update'], ['as' => 'admin.users.update']);
        $routes->delete('users/(:num)', [Users::class, 'delete'], ['as' => 'admin.users.delete']);

        $routes->get('content-types', [ContentTypes::class, 'index'], ['as' => 'admin.types']);
        $routes->get('content-types/create', [ContentTypes::class, 'create'], ['as' => 'admin.types.create']);
        $routes->post('content-types', [ContentTypes::class, 'store'], ['as' => 'admin.types.store']);
        $routes->get('content-types/(:num)/edit', [ContentTypes::class, 'edit'], ['as' => 'admin.types.edit']);
        $routes->put('content-types/(:num)', [ContentTypes::class, 'update'], ['as' => 'admin.types.update']);
        $routes->delete('content-types/(:num)', [ContentTypes::class, 'delete'], ['as' => 'admin.types.delete']);

        $routes->get('activity-log', [ActivityLog::class, 'index'], ['as' => 'admin.activity']);

        // バックアップ: download the database (+ uploads)
        $routes->get('backup', [Backup::class, 'index'], ['as' => 'admin.backup']);
        $routes->post('backup', [Backup::class, 'download'], ['as' => 'admin.backup.download']);
    });
});
