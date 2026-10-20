<?php

namespace App\Controller;

use App\Entities\Entry;
use App\Model\EntryModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Read-only JSON API for other websites and apps:
 *
 *   GET /api/site                     site name, tagline, logo, and the content types listed below
 *   GET /api/{type}?page=1&limit=10   published entries of a content type, newest first
 *   GET /api/{type}/{slug}            one published entry
 *
 * Only content types with "Expose REST API" switched on (Settings → Content Types), or listed in
 * cms.apiTypes (.env), are readable — and only what visitors can already see
 * (published, publish date reached, not ended). Never drafts, users, inquiries or settings.
 * Optional: cms.apiKey (key required), cms.apiOrigins (CORS for browser JavaScript on other sites).
 */
class Api extends BaseController
{
    /** Requests per minute per IP. */
    private const RATE_LIMIT = 120;

    private const MAX_LIMIT = 50;

    public function site(): ResponseInterface
    {
        if ($error = $this->guard()) {
            return $error;
        }

        $logo  = setting('site_logo', '');
        $types = [];
        foreach ($this->exposedTypes() as $slug) {
            if (content_type_exists($slug)) {
                $type    = model(\App\Model\ContentTypeModel::class)->findBySlug($slug);
                $types[] = ['slug' => $slug, 'name' => $type->name, 'url' => site_url('api/' . $slug)];
            }
        }

        return $this->json([
            'data' => [
                'name'    => setting('site_name', config('Cms')->appName),
                'tagline' => setting('site_tagline', ''),
                'url'     => base_url(),
                'logo'    => $logo ? media_url($logo) : null,
                'types'   => $types,
            ],
        ]);
    }

    public function index(string $slug): ResponseInterface
    {
        if ($error = $this->guard($slug)) {
            return $error;
        }

        $limit = max(1, min(self::MAX_LIMIT, (int) ($this->request->getGet('limit') ?: 10)));
        $page  = max(1, (int) ($this->request->getGet('page') ?: 1));
        $model = EntryModel::for($slug);
        $items = $model->published()->latestPublished()->paginate($limit, 'default', $page);
        $total = $model->pager->getTotal();
        $pages = (int) max(1, ceil($total / $limit));
        $link  = fn (int $p) => site_url('api/' . $slug) . '?' . http_build_query(['page' => $p, 'limit' => $limit]);

        return $this->json([
            'data'  => array_map(fn (Entry $e) => $this->entry($e, $model), $items),
            'meta'  => ['page' => $page, 'limit' => $limit, 'total' => $total, 'pages' => $pages],
            'links' => ['prev' => $page > 1 ? $link($page - 1) : null, 'next' => $page < $pages ? $link($page + 1) : null],
        ]);
    }

    public function show(string $slug, string $entrySlug): ResponseInterface
    {
        if ($error = $this->guard($slug)) {
            return $error;
        }

        $model = EntryModel::for($slug);
        $entry = $model->findPublished($entrySlug);

        return $entry === null
            ? $this->error(404, 'Not found.')
            : $this->json(['data' => $this->entry($entry, $model)]);
    }

    /**
     * CORS preflight (browsers ask before sending the X-API-Key header from another site).
     */
    public function preflight(): ResponseInterface
    {
        return $this->cors($this->response->setStatusCode(204));
    }

    // ------------------------------------------------------------------ checks

    /**
     * Key, rate limit and content type checks; an error response, or null when the request may go on.
     */
    private function guard(?string $slug = null): ?ResponseInterface
    {
        $key = config('Cms')->apiKey;
        if ($key !== '') {
            $given = (string) ($this->request->getHeaderLine('X-API-Key') ?: $this->request->getGet('api_key'));
            if (! hash_equals($key, $given)) {
                return $this->error(401, 'A valid API key is required.');
            }
        }

        $throttler = service('throttler');
        if (! $throttler->check('api-' . md5($this->request->getIPAddress()), self::RATE_LIMIT, MINUTE)) {
            return $this->error(429, 'Too many requests.')->setHeader('Retry-After', (string) max(1, $throttler->getTokenTime()));
        }

        if ($slug !== null && (! in_array($slug, $this->exposedTypes(), true) || ! content_type_exists($slug))) {
            return $this->error(404, 'Not found.');
        }

        return null;
    }

    /**
     * Slugs of the readable content types: the admin switch, plus any listed in cms.apiTypes.
     *
     * @return list<string>
     */
    private function exposedTypes(): array
    {
        $slugs = array_filter(array_map('trim', explode(',', config('Cms')->apiTypes)));

        foreach (model(\App\Model\ContentTypeModel::class)->allBySlug() as $slug => $type) {
            if (! empty($type->api_enabled)) {
                $slugs[] = $slug;
            }
        }

        return array_values(array_unique($slugs));
    }

    // ------------------------------------------------------------------ output

    private function json(array $body): ResponseInterface
    {
        return $this->cors($this->response
            ->setJSON($body)
            ->setHeader('Cache-Control', 'public, max-age=60'));
    }

    private function error(int $status, string $message): ResponseInterface
    {
        return $this->cors($this->response
            ->setStatusCode($status)
            ->setJSON(['error' => ['status' => $status, 'message' => $message]])
            ->setHeader('Cache-Control', 'no-store'));
    }

    private function cors(ResponseInterface $response): ResponseInterface
    {
        $origin  = $this->request->getHeaderLine('Origin');
        $allowed = array_filter(array_map('trim', explode(',', config('Cms')->apiOrigins)));

        if ($origin !== '' && (in_array('*', $allowed, true) || in_array($origin, $allowed, true))) {
            $response->setHeader('Access-Control-Allow-Origin', in_array('*', $allowed, true) ? '*' : $origin)
                ->setHeader('Access-Control-Allow-Methods', 'GET, OPTIONS')
                ->setHeader('Access-Control-Allow-Headers', 'X-API-Key')
                ->setHeader('Access-Control-Max-Age', '600');
        }

        return $response->setHeader('Vary', 'Origin');
    }

    /**
     * One entry as JSON: base columns, SEO, and every custom field by its name.
     */
    private function entry(Entry $entry, EntryModel $model): array
    {
        $type   = $model->contentType();
        $detail = route_to($type->slug . '.detail', $entry->slug);

        return [
            'id'           => (int) $entry->id,
            'slug'         => $entry->slug,
            'title'        => $entry->title,
            'url'          => $detail ? site_url(ltrim($detail, '/')) : null,
            'published_at' => $this->date($entry->published_at),
            'updated_at'   => $this->date($entry->updated_at),
            'seo'          => ['title' => $entry->meta_title ?: null, 'description' => $entry->meta_description ?: null],
            'fields'       => $this->fields($type->fields ?? [], $entry->values()),
        ];
    }

    private function date(mixed $value): ?string
    {
        return $value ? date(DATE_ATOM, strtotime((string) $value)) : null;
    }

    /**
     * Values by field type: images/files as full URLs, choices with their labels, relations as
     * {id, slug, title}, groups as objects and repeaters as lists (recursively).
     */
    private function fields(array $definitions, array $values): array
    {
        $out = [];

        foreach ($definitions as $field) {
            $value = $values[$field['name']] ?? null;
            $out[$field['name']] = match ($field['type']) {
                'image', 'file' => $value ? media_url((string) $value) : null,
                'select', 'radio' => $value === null || $value === '' ? null : ['value' => $value, 'label' => $this->label($field, $value)],
                'checkbox' => array_map(fn ($v) => ['value' => $v, 'label' => $this->label($field, $v)], (array) ($value ?? [])),
                'toggle'   => (bool) $value,
                'number'   => $value === null || $value === '' ? null : $value + 0,
                'editor'   => $this->absoluteUrls((string) $value),
                'relation' => $this->relation($field, $value),
                'group'    => $this->fields($field['sub_fields'] ?? [], is_array($value) ? $value : []),
                'repeater' => array_map(fn ($row) => $this->fields($field['sub_fields'] ?? [], (array) $row), array_values(array_filter((array) ($value ?? []), 'is_array'))),
                default    => $value,
            };
        }

        return $out;
    }

    private function label(array $field, mixed $value): string
    {
        foreach ($field['choices'] ?? [] as $choice) {
            if ((string) $choice['value'] === (string) $value) {
                return (string) $choice['label'];
            }
        }

        return (string) $value;
    }

    /**
     * Linked entries (published only, in the chosen order) as {id, slug, title}.
     */
    private function relation(array $field, mixed $value): array|null
    {
        $ids      = array_map('intval', (array) ($value ?? []));
        $multiple = ! empty($field['multiple']);
        $slug     = (string) ($field['related_type'] ?? '');

        if ($ids === [] || $slug === '' || ! content_type_exists($slug)) {
            return $multiple ? [] : null;
        }

        $found = [];
        foreach (EntryModel::for($slug)->published()->whereIn('id', $ids)->findAll() as $e) {
            $found[(int) $e->id] = ['id' => (int) $e->id, 'slug' => $e->slug, 'title' => $e->title];
        }
        $list = array_values(array_filter(array_map(static fn ($id) => $found[$id] ?? null, $ids)));

        return $multiple ? $list : ($list[0] ?? null);
    }

    /**
     * Editor HTML with relative upload links made absolute, so it works on another site.
     */
    private function absoluteUrls(string $html): string
    {
        return preg_replace_callback(
            '/\b(src|href)=(["\'])(?!https?:|\/\/|#|mailto:|tel:|data:)\/?(uploads\/[^"\']*)\2/i',
            static fn ($m) => $m[1] . '=' . $m[2] . base_url($m[3]) . $m[2],
            $html,
        );
    }
}
