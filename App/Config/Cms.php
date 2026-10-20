<?php

/*
 |    ____                               ____
 |   / ___|  _   _  _ __   ___ __  __   / ___|  ___   _ __   ___
 |   \___ \ | | | || '__| / _ \\ \/ /  | |     / _ \ | '__| / _ \
 |    ___) || |_| || |   |  __/ >  <   | |___ | (_) || |   |  __/
 |   |____/  \__,_||_|    \___|/_/\_\   \____| \___/ |_|    \___|
 |
 |  Lightweight CMS for shared hosting · CodeIgniter 4 + Blade
 |  Core settings of SurexCore. Site settings go in .env.
 */

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Cms extends BaseConfig
{
    /**
     * CMS version shown in the admin footer.
     */
    public string $version = '0.1.7';

    /**
     * Product name and developer credit (welcome page). Constants: only changeable here in the code.
     */
    public const NAME      = 'SurexCore';
    public const DEVELOPER = 'Suresh Mayanglambam';

    /**
     * Name of the CMS in the admin panel and default sender name for email (.env: cms.appName).
     */
    public string $appName = 'SurexCore';

    /**
     * URL segment of the admin panel: https://example.com/{adminPath}/login (.env: cms.adminPath).
     * Letters, numbers, - and _ only. Changing it hides /admin (it then shows the 404 page).
     */
    public string $adminPath = 'admin';

    /**
     * Where site notifications go, e.g. contact form mail (.env: cms.adminEmail).
     */
    public string $adminEmail = '';

    /**
     * Failed logins for one login ID (from any IP) before the admins get an alert email.
     * 0 = never (.env: cms.loginAlertAfter).
     */
    public int $loginAlertAfter = 3;

    /**
     * Failed logins for one login ID before it is locked for $loginLockMinutes (from any IP).
     * 0 = never lock (.env: cms.loginLockAfter, cms.loginLockMinutes).
     */
    public int $loginLockAfter   = 10;
    public int $loginLockMinutes = 15;

    /**
     * Failed logins from one IP (within $loginLockMinutes of each other) before that IP is blocked from
     * the admin panel until it is removed from the list: Settings → Security, or
     * writable/security/blocked-ips.txt. 0 = never block (.env: cms.ipBlockAfter).
     */
    public int $ipBlockAfter = 3;

    /**
     * Images uploaded in the admin (media, image fields, editor, logo) are saved as WebP:
     * JPEG as lossy WebP at $webpQuality, PNG as lossless WebP (transparency kept).
     * A file is only converted when the WebP is smaller; GIFs stay GIF (animation).
     * (.env: cms.convertToWebp = false to keep the original formats.)
     */
    public bool $convertToWebp = true;
    public int $webpQuality    = 82;

    /**
     * Read-only JSON API (App\Controller\Api): content types readable at /api/{slug}, comma separated,
     * e.g. 'news,events'. Only published entries; nothing is exposed until listed here (.env: cms.apiTypes).
     * /api/site (site name, tagline, logo) is always available.
     */
    public string $apiTypes = '';

    /**
     * Other websites whose browser JavaScript may call the API (CORS), comma separated origins,
     * e.g. 'https://example.com,https://www.example.com'; '*' = any site. Server-side calls (PHP)
     * don't need this (.env: cms.apiOrigins).
     */
    public string $apiOrigins = '';

    /**
     * When set, every API request needs it: header "X-API-Key: …" (or ?api_key=…). Empty = open read
     * access, which is fine for content that is public on the website anyway (.env: cms.apiKey).
     */
    public string $apiKey = '';

    /**
     * Template the installer fills in to create .env.
     */
    public string $envTemplate = ROOTPATH . '.env.example';

    /**
     * Created by the installer. While it is missing, every request is sent to /install.
     */
    public string $lockFile = WRITEPATH . 'installed.lock';

    /**
     * Where the installer writes database credentials.
     */
    public string $envFile = ROOTPATH . '.env';

    /**
     * Blade templates (View/*.blade.php) and their compiled cache.
     */
    public string $viewPath  = ROOTPATH . 'View';
    public string $bladeCache = WRITEPATH . 'cache' . DIRECTORY_SEPARATOR . 'blade';

    /**
     * User roles.
     *  admin    – everything, including Settings (users, content types, activity log). Email required.
     *  webadmin – manages entries only (for clients). Logs in with a login ID or email.
     */
    public array $roles = [
        'admin'    => '管理者',
        'webadmin' => 'Web管理者',
    ];

    /**
     * URL segments a content type slug may not use (they would clash with built-in routes).
     */
    public array $reservedSlugs = ['admin', 'install', 'assets', 'api', 'index', 'branding', 'uploads'];

    public function __construct()
    {
        parent::__construct();

        // Fall back to "admin" if .env has an unusable value.
        $path            = trim($this->adminPath, " /");
        $this->adminPath = preg_match('/^[A-Za-z0-9_-]+$/', $path) === 1 ? $path : 'admin';
    }
}
