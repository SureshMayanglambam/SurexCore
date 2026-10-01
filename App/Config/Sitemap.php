<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * /sitemap.xml and /robots.txt (App\Controller\Seo).
 *
 * The sitemap is built automatically:
 *   - pages: every GET route in routes/web.php without placeholders ("/", "news", "contact", ...)
 *   - entries: published entries of each content type that has a detail route named "{slug}.detail"
 *     (e.g. news → url_to('news.detail', $slug)). Types without one (e.g. 沿革) are skipped.
 *
 * Search engines are kept out completely with Settings → General → 検索エンジンにインデックスさせない.
 */
class Sitemap extends BaseConfig
{
    /**
     * Pages to leave out of the sitemap: route names or paths.
     *
     * @var list<string>
     */
    public array $exclude = [
        'install',
        'contact.confirm',
        'contact.back',
        'contact.thanks',
    ];

    /**
     * Detail route per content type, when it isn't "{slug}.detail":
     *   'products' => 'shop.item',
     * Set a type to null to leave its entries out.
     *
     * @var array<string, string|null>
     */
    public array $detailRoutes = [];

    /**
     * Extra lines for robots.txt, e.g. 'Disallow: /search'.
     * (The admin panel is not listed on purpose: that would reveal its URL. Its pages are noindex.)
     *
     * @var list<string>
     */
    public array $robotsRules = [];
}
