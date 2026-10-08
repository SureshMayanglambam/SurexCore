<?php

namespace App\Admin;

use App\Entities\Entry;
use App\Libraries\Fields;
use App\Model\ContentTypeModel;
use App\Model\EntryModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * List / create / edit / delete entries of any content type: /admin/content/{type}.
 * The form is generated from the type's custom fields (Settings → Content Types);
 * entries are stored in the type's own table (EntryModel::for()).
 */
class Entries extends AdminController
{
    /** Fixed inputs every entry has, besides its custom fields. */
    private const RULES = [
        'slug'             => ['label' => 'スラッグ', 'rules' => 'permit_empty|max_length[191]'],
        'status'           => ['label' => 'ステータス', 'rules' => 'required|in_list[draft,published]'],
        'published_at'     => ['label' => '公開日時', 'rules' => 'permit_empty|valid_date[Y-m-d\TH:i]'],
        'published_until'  => ['label' => '公開終了日時', 'rules' => 'permit_empty|valid_date[Y-m-d\TH:i]'],
        'meta_title'       => ['label' => 'メタタイトル', 'rules' => 'permit_empty|max_length[255]'],
        'meta_description' => ['label' => 'メタディスクリプション', 'rules' => 'permit_empty|max_length[500]'],
    ];

    public function index(string $typeSlug): string
    {
        $type   = $this->findType($typeSlug);
        $model  = EntryModel::for($type);
        $table  = $model->tableName();
        $status = $this->request->getGet('status');
        $search = trim((string) $this->request->getGet('q'));
        $from   = $this->validDate($this->request->getGet('from'));
        $to     = $this->validDate($this->request->getGet('to'));

        $trash = $this->request->getGet('trash') === '1';

        $model->withAuthor();
        if ($trash) {
            $model->onlyDeleted();
        }

        if (in_array($status, ['draft', 'published'], true)) {
            $model->where("{$table}.status", $status);
        }
        if ($search !== '') {
            $model->like("{$table}.title", $search);
        }
        // Publish date range (drafts without a date are excluded when filtering by date)
        if ($from !== null) {
            $model->where("{$table}.published_at >=", $from . ' 00:00:00');
        }
        if ($to !== null) {
            $model->where("{$table}.published_at <=", $to . ' 23:59:59');
        }

        return $this->render('admin.entries.index', [
            'type'       => $type,
            'items'      => $model->orderBy("{$table}.updated_at", 'DESC')->paginate(20),
            'pager'      => $model->pager,
            'status'     => $status,
            'search'     => $search,
            'from'       => $from,
            'to'         => $to,
            'filtered'   => $status || $search !== '' || $from || $to,
            'trash'      => $trash,
            'trashCount' => EntryModel::for($type)->onlyDeleted()->countAllResults(),
            // Fields switched to "Show in list" in the content type's field builder
            'listFields' => array_values(array_filter($type->fields, static fn ($f) => ! empty($f['show_in_list']))),
        ]);
    }

    /**
     * A Y-m-d date from the query string, or null.
     */
    private function validDate(mixed $value): ?string
    {
        $date = is_string($value) ? \DateTime::createFromFormat('!Y-m-d', $value) : false;

        return $date && $date->format('Y-m-d') === $value ? $value : null;
    }

    public function create(string $typeSlug): string
    {
        $type = $this->findType($typeSlug);

        return $this->form($type, null, service('fields')->defaults($type->fields));
    }

    public function store(string $typeSlug): RedirectResponse
    {
        $type    = $this->findType($typeSlug);
        $payload = $this->payload($type);

        if ($payload instanceof RedirectResponse) {
            return $payload;
        }

        [$data, $values] = $payload;
        $model           = EntryModel::for($type);

        $data['author_id'] = current_user()->id;
        $data['slug']      = $model->uniqueSlug($data['slug']);

        $id = $model->saveEntry($data, $values);

        if ($data['title'] === '') {
            $data['title'] = "{$type->singular} #{$id}";
            EntryModel::for($type)->update($id, ['title' => $data['title']]);
        }

        log_activity('entry.created', "{$type->singular}「{$data['title']}」を作成しました", $type->slug, $id);

        return redirect()->route('admin.entries.edit', [$type->slug, $id])->with('success', "{$type->singular}を作成しました。");
    }

    public function edit(string $typeSlug, int $id): string
    {
        $type = $this->findType($typeSlug);
        $item = $this->findEntry($type, $id);

        // Fields added to the type after this entry was saved start with their defaults.
        $values = array_replace(service('fields')->defaults($type->fields), $item->values());

        return $this->form($type, $item, $values);
    }

    public function update(string $typeSlug, int $id): RedirectResponse
    {
        $type    = $this->findType($typeSlug);
        $item    = $this->findEntry($type, $id);
        $payload = $this->payload($type, $item);

        if ($payload instanceof RedirectResponse) {
            return $payload;
        }

        [$data, $values] = $payload;
        $model           = EntryModel::for($type);

        $data['slug'] = $model->uniqueSlug($data['slug'], $item->id);
        if ($data['title'] === '') {
            $data['title'] = "{$type->singular} #{$item->id}";
        }

        $model->saveEntry($data, $values, $item->id);
        log_activity('entry.updated', "{$type->singular}「{$data['title']}」を更新しました", $type->slug, $item->id);

        return redirect()->route('admin.entries.edit', [$type->slug, $item->id])->with('success', "{$type->singular}を更新しました。");
    }

    public function delete(string $typeSlug, int $id): RedirectResponse
    {
        $type = $this->findType($typeSlug);
        $item = $this->findEntry($type, $id);

        EntryModel::for($type)->delete($item->id);
        log_activity('entry.deleted', "{$type->singular}「{$item->title}」をゴミ箱に移動しました", $type->slug, $item->id);

        return redirect()->route('admin.entries', [$type->slug])->with('success', "「{$item->title}」をゴミ箱に移動しました。");
    }

    /**
     * ゴミ箱 → 復元
     */
    public function restore(string $typeSlug, int $id): RedirectResponse
    {
        $type = $this->findType($typeSlug);
        $item = $this->findTrashed($type, $id);

        $model = EntryModel::for($type);
        $model->builder()->where('id', $item->id)->update(['deleted_at' => null]);
        log_activity('entry.restored', "{$type->singular}「{$item->title}」をゴミ箱から復元しました", $type->slug, $item->id);

        return redirect()->to(url_to('admin.entries', $type->slug) . '?trash=1')->with('success', "「{$item->title}」を復元しました。");
    }

    /**
     * ゴミ箱 → 完全に削除 (repeater rows go with it: ON DELETE CASCADE)
     */
    public function purge(string $typeSlug, int $id): RedirectResponse
    {
        $type = $this->findType($typeSlug);
        $item = $this->findTrashed($type, $id);

        EntryModel::for($type)->delete($item->id, true);
        log_activity('entry.purged', "{$type->singular}「{$item->title}」を完全に削除しました", $type->slug, $item->id);

        return redirect()->to(url_to('admin.entries', $type->slug) . '?trash=1')->with('success', "「{$item->title}」を完全に削除しました。");
    }

    /**
     * ゴミ箱を空にする
     */
    public function emptyTrash(string $typeSlug): RedirectResponse
    {
        $type  = $this->findType($typeSlug);
        $count = EntryModel::for($type)->onlyDeleted()->countAllResults();

        if ($count > 0) {
            EntryModel::for($type)->purgeDeleted();
            log_activity('entry.purged', "{$type->name}のゴミ箱を空にしました（{$count}件）", $type->slug);
        }

        return redirect()->route('admin.entries', [$type->slug])->with('success', "ゴミ箱を空にしました（{$count}件）。");
    }

    /**
     * 複製: copy an entry (all field values, repeater rows, SEO) as a new draft and open it.
     */
    public function duplicate(string $typeSlug, int $id): RedirectResponse
    {
        $type   = $this->findType($typeSlug);
        $item   = $this->findEntry($type, $id);
        $values = $item->values();
        $model  = EntryModel::for($type);

        // Mark the copy in its title field, so the two are easy to tell apart in the list.
        $titleField = $this->titleFieldName($type);
        if ($titleField !== null && trim((string) ($values[$titleField] ?? '')) !== '') {
            $values[$titleField] = trim((string) $values[$titleField]) . '（コピー）';
        }
        $title = $this->titleFrom($type, $values) ?: $item->title . '（コピー）';

        $newId = $model->saveEntry([
            'title'            => mb_substr($title, 0, 255),
            'slug'             => $model->uniqueSlug(mb_substr($item->slug, 0, 170) . '-copy'),
            'status'           => 'draft',
            'published_at'     => null,
            'published_until'  => null,
            'author_id'        => current_user()->id,
            'meta_title'       => $item->meta_title,
            'meta_description' => $item->meta_description,
        ], $values);

        log_activity('entry.duplicated', "{$type->singular}「{$item->title}」を複製しました", $type->slug, $newId);

        return redirect()->route('admin.entries.edit', [$type->slug, $newId])
            ->with('success', "「{$item->title}」を複製しました。下書きとして保存されています。");
    }

    private function form(object $type, ?object $item, array $values): string
    {
        // After a failed save, show what was submitted.
        $old = session('_ci_old_input');
        if (isset($old['post']['fields']) && is_array($old['post']['fields'])) {
            $values = $old['post']['fields'];
        }

        return $this->render('admin.entries.form', [
            'type'   => $type,
            'item'   => $item,
            'values' => $values,
        ]);
    }

    /**
     * プレビュー: render the frontend template with the form's current (unsaved) values.
     * Nothing is saved; incomplete input is shown as it is.
     */
    public function preview(string $typeSlug, ?int $id = null): ResponseInterface
    {
        $type    = $this->findType($typeSlug);
        $setting = trim((string) ($type->preview_view ?? ''));
        $isPage  = str_starts_with($setting, '/');
        $view    = $isPage ? null : $this->previewView($type);

        if (($isPage && self::pageRoute($setting) === null) || (! $isPage && $view === null)) {
            return $this->response->setStatusCode(404)->setBody($this->render('admin.entries.preview-missing', [
                'type'     => $type,
                'expected' => $isPage ? $setting : 'View/frontend/' . $type->slug . '/detail.blade.php',
            ]));
        }

        // Validation errors are ignored: a preview may show half-finished content.
        $errors = [];
        $values = service('fields')->sanitizeValues($type->fields, $this->request->getPost('fields'), $errors);
        $title  = $this->titleFrom($type, $values) ?: 'プレビュー';
        $date   = (string) $this->request->getPost('published_at');

        $item = (new Entry())->injectRawData([
            'id'               => $id ?? 0,
            'title'            => $title,
            'slug'             => (string) $this->request->getPost('slug') ?: url_title($title, '-', true),
            'status'           => (string) $this->request->getPost('status') ?: 'draft',
            'published_at'     => $date !== '' ? date('Y-m-d H:i:s', strtotime($date)) : date('Y-m-d H:i:s'),
            'published_until'  => (string) $this->request->getPost('published_until') !== '' ? date('Y-m-d H:i:s', strtotime((string) $this->request->getPost('published_until'))) : null,
            'meta_title'       => (string) $this->request->getPost('meta_title'),
            'meta_description' => (string) $this->request->getPost('meta_description'),
            'author_name'      => current_user()->name,
            'created_at'       => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
        ]);
        $item->setStore(EntryModel::for($type))->setValues($values);

        if ($isPage) {
            // A list page (e.g. /recruit): run its controller; this entry replaces its saved version in the page's data.
            EntryModel::previewWith($type->slug, $item);
            $html = $this->renderPage(self::pageRoute($setting));
        } else {
            // Same variables as the public pages get from FrontController.
            $html = blade($view, [
                'item' => $item,
                'site' => (object) ['name' => setting('site_name', 'SurexCore'), 'tagline' => setting('site_tagline', '')],
            ]);
        }

        return $this->response
            ->setHeader('X-Robots-Tag', 'noindex, nofollow')
            ->setBody($this->withPreviewBar($html, $type));
    }

    /**
     * The public GET route of a page path ("/recruit"): [controller, method, params], or null.
     * Admin pages are never used.
     */
    public static function pageRoute(string $path): ?array
    {
        $path  = trim($path, '/');
        $admin = trim(config('Cms')->adminPath, '/');
        if ($path === $admin || str_starts_with($path, $admin . '/')) {
            return null;
        }

        // The preview itself is a POST/PUT request: look the page up among the GET routes.
        $routes = service('routes');
        $verb   = $routes->getHTTPVerb();
        $routes->setHTTPVerb('GET');

        try {
            $router = single_service('router', $routes, service('request')->withMethod('GET'));
            $router->handle($path === '' ? '/' : $path);

            return [$router->controllerName(), $router->methodName(), $router->params()];
        } catch (\Throwable) {
            return null;
        } finally {
            $routes->setHTTPVerb($verb);
        }
    }

    /**
     * HTML of a public page, rendered by its own controller.
     */
    private function renderPage(array $route): string
    {
        [$controller, $method, $params] = $route;

        if ($controller instanceof \Closure) {
            $output = $controller(...$params);
        } else {
            $page = new $controller();
            $page->initController($this->request, $this->response, service('logger'));
            $output = $page->{$method}(...$params);
        }

        return $output instanceof ResponseInterface ? (string) $output->getBody() : (string) $output;
    }

    /**
     * Frontend template for previews: the one set on the content type, or frontend.{slug}.detail.
     */
    private function previewView(object $type): ?string
    {
        $view = ($type->preview_view ?? '') !== '' ? $type->preview_view : 'frontend.' . $type->slug . '.detail';

        return is_file(ROOTPATH . 'View/' . str_replace('.', '/', $view) . '.blade.php') ? $view : null;
    }

    /**
     * Fixed bar at the top of the preview, so it can't be mistaken for the live page.
     */
    private function withPreviewBar(string $html, object $type): string
    {
        $bar = '<div id="wd-preview-bar" style="position:fixed;z-index:2147483647;left:0;right:0;top:0;display:flex;align-items:center;'
            . 'justify-content:space-between;gap:1rem;padding:.55rem 1rem;background:#072F1F;color:#fff;font:600 13px/1.4 system-ui,sans-serif;'
            . 'box-shadow:0 4px 16px rgba(0,0,0,.2)">'
            . '<span><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#B4F105;margin-right:.5rem"></span>'
            . 'プレビュー（' . esc($type->name) . '）— 未保存の内容を表示しています。公開・保存はされていません。</span>'
            . '<button type="button" onclick="document.getElementById(\'wd-preview-bar\').remove();document.getElementById(\'wd-preview-space\').remove()" '
            . 'style="background:none;border:1px solid rgba(255,255,255,.3);color:#fff;border-radius:6px;padding:.15rem .6rem;cursor:pointer">閉じる</button>'
            . '</div>'
            // Push the page down so the bar doesn't cover the site header.
            . '<style id="wd-preview-space">html{scroll-padding-top:48px}body{margin-top:44px!important}</style>';

        return str_contains($html, '</body>') ? str_replace('</body>', $bar . '</body>', $html) : $html . $bar;
    }

    private function findType(string $slug): object
    {
        return model(ContentTypeModel::class)->findBySlug($slug)
            ?? throw PageNotFoundException::forPageNotFound();
    }

    private function findEntry(object $type, int $id): object
    {
        return EntryModel::for($type)->find($id)
            ?? throw PageNotFoundException::forPageNotFound();
    }

    private function findTrashed(object $type, int $id): object
    {
        return EntryModel::for($type)->onlyDeleted()->find($id)
            ?? throw PageNotFoundException::forPageNotFound();
    }

    /**
     * [built-in columns, nested field values], or a redirect back with errors.
     */
    private function payload(object $type, ?object $existing = null): array|RedirectResponse
    {
        if (! $this->validate(self::RULES)) {
            $errors = array_values($this->validator->getErrors());
        }

        $errors ??= [];
        $values   = service('fields')->sanitizeValues($type->fields, $this->request->getPost('fields'), $errors);

        $data = $this->validator->getValidated();

        // 公開終了日時 (optional): empty = no end. It must come after the publish date.
        $until = ($data['published_until'] ?? '') !== '' ? date('Y-m-d H:i:s', strtotime($data['published_until'])) : null;
        $from  = ($data['published_at'] ?? '') !== '' ? date('Y-m-d H:i:s', strtotime($data['published_at'])) : date('Y-m-d H:i:s');
        if ($until !== null && $until <= $from) {
            $errors[] = '公開終了日時は公開日時より後に設定してください。';
        }

        if ($errors !== []) {
            return $this->backWithErrors($errors);
        }

        $title = $this->titleFrom($type, $values);

        $slug = mb_substr(url_title(trim($data['slug'] ?? '') ?: $title, '-', true), 0, 180);
        if ($slug === '') {
            $slug = $type->slug . '-' . date('YmdHis');
        }

        $publishedAt = ($data['published_at'] ?? '') !== ''
            ? date('Y-m-d H:i:s', strtotime($data['published_at']))
            : null;

        // First publish without a date: publish now.
        if ($data['status'] === 'published' && $publishedAt === null) {
            $publishedAt = $existing->published_at ?? date('Y-m-d H:i:s');
        }

        return [[
            'title'            => mb_substr($title, 0, 255),
            'slug'             => $slug,
            'status'           => $data['status'],
            'published_at'     => $publishedAt,
            'published_until'  => $until,
            'meta_title'       => trim($data['meta_title'] ?? ''),
            'meta_description' => trim($data['meta_description'] ?? ''),
        ], $values];
    }

    /**
     * The entry title comes from the type's title field (or its first text field).
     */
    private function titleFrom(object $type, array $values): string
    {
        $name = $this->titleFieldName($type);

        return $name !== null ? trim((string) ($values[$name] ?? '')) : '';
    }

    /**
     * Name of the type's title field (or its first text field), or null.
     */
    private function titleFieldName(object $type): ?string
    {
        $candidates = service('fields')->titleCandidates($type->fields);

        return isset($candidates[$type->title_field ?? '']) ? $type->title_field : array_key_first($candidates);
    }
}
