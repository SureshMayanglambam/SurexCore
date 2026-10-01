<?php

namespace App\Controller;

use App\Model\ContentTypeModel;
use App\Model\EntryModel;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Sitemap;

/**
 * /sitemap.xml and /robots.txt, generated from the routes and published entries.
 * What goes in is configured in App/Config/Sitemap.php.
 */
class Seo extends BaseController
{
    public function sitemap(): ResponseInterface
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($this->urls() as $url) {
            $xml .= '  <url><loc>' . esc($url['loc']) . '</loc>'
                . ($url['lastmod'] ? '<lastmod>' . date('c', strtotime($url['lastmod'])) . '</lastmod>' : '')
                . "</url>\n";
        }

        return $this->response
            ->setContentType('application/xml', 'UTF-8')
            ->setBody($xml . '</urlset>' . "\n");
    }

    public function robots(): ResponseInterface
    {
        if (setting('search_noindex') === '1') {
            $lines = ['User-agent: *', 'Disallow: /'];
        } else {
            $lines = ['User-agent: *', 'Disallow:', ...config(Sitemap::class)->robotsRules, '', 'Sitemap: ' . url_to('sitemap')];
        }

        return $this->response
            ->setContentType('text/plain', 'UTF-8')
            ->setBody(implode("\n", $lines) . "\n");
    }

    /**
     * @return list<array{loc: string, lastmod: string|null}>
     */
    private function urls(): array
    {
        $config  = config(Sitemap::class);
        $entries = $this->publishedEntries($config);

        // Last update per type, used as lastmod of its list page ("news" → newest news entry).
        $latest = array_map(static fn ($rows) => $rows === [] ? null : max(array_column($rows, 'updated_at')), $entries);

        $urls = [];
        foreach ($this->pages($config) as $path) {
            $urls[] = [
                'loc'     => base_url($path === '/' ? '' : $path),
                'lastmod' => $path === '/' ? (array_filter($latest) ? max(array_filter($latest)) : null) : ($latest[$path] ?? null),
            ];
        }

        foreach ($entries as $slug => $rows) {
            foreach ($rows as $row) {
                $urls[] = ['loc' => url_to($this->detailRoute($config, $slug), $row['slug']), 'lastmod' => $row['updated_at']];
            }
        }

        return $urls;
    }

    /**
     * Paths of the public GET routes without placeholders ("/", "news", "contact", ...).
     *
     * @return list<string>
     */
    private function pages(Sitemap $config): array
    {
        $routes = service('routes');
        $admin  = trim(config('Cms')->adminPath, '/');
        $pages  = [];

        foreach (array_keys($routes->getRoutes('GET')) as $path) {
            $name = $routes->getRoutesOptions($path, 'GET')['as'] ?? $path;

            if (preg_match('/[()\[\]*?+\\\\]/', $path)                    // has placeholders
                || str_starts_with($path, '__')                              // framework routes (__hot-reload)
                || $path === $admin || str_starts_with($path, $admin . '/')
                || in_array($path, ['sitemap.xml', 'robots.txt'], true)
                || in_array($path, $config->exclude, true) || in_array($name, $config->exclude, true)) {
                continue;
            }
            $pages[] = $path;
        }

        return $pages;
    }

    /**
     * slug => published entries (slug, updated_at) of each content type that has a detail page.
     *
     * @return array<string, list<array{slug: string, updated_at: string}>>
     */
    private function publishedEntries(Sitemap $config): array
    {
        $out = [];

        foreach (model(ContentTypeModel::class)->allBySlug() as $slug => $type) {
            $route = $this->detailRoute($config, $slug);

            if ($route === null || route_to($route, 'x') === false) {
                continue;   // no public detail page (e.g. 沿革 shown only on the top page)
            }

            $model      = EntryModel::for($type);
            $table      = $model->tableName();
            $out[$slug] = $model->published()
                ->select("{$table}.slug, {$table}.updated_at")
                ->orderBy("{$table}.published_at", 'DESC')
                ->asArray()
                ->findAll();
        }

        return $out;
    }

    private function detailRoute(Sitemap $config, string $slug): ?string
    {
        return array_key_exists($slug, $config->detailRoutes) ? $config->detailRoutes[$slug] : $slug . '.detail';
    }
}
