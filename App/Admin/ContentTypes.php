<?php

namespace App\Admin;

use App\Libraries\ContentSchema;
use App\Libraries\Fields;
use App\Model\ContentTypeModel;
use App\Model\EntryModel;
use App\Model\SettingModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Settings → Content Types (Admin only). Each type gets its own menu item, entry screens and
 * database table automatically; saving a type creates/alters its table (App\Libraries\ContentSchema).
 */
class ContentTypes extends AdminController
{
    public function index(): string
    {
        $types  = model(ContentTypeModel::class)->allBySlug();
        $counts = [];

        foreach ($types as $slug => $type) {
            $counts[$slug] = EntryModel::for($type)->countAllResults();
        }

        return $this->render('admin.types.index', ['types' => $types, 'counts' => $counts]);
    }

    public function create(): string
    {
        return $this->form(null);
    }

    public function store(): RedirectResponse
    {
        $data = $this->validated();

        if (! is_array($data)) {
            return $data;
        }

        $changes = $this->applySchema((object) $data, null);
        if (! is_array($changes)) {
            return $changes;
        }

        $id = (int) model(ContentTypeModel::class)->insert($data);
        $this->rememberSchema();
        log_activity('type.created', "コンテンツタイプ「{$data['name']}」を作成しました（" . implode('、', $changes) . '）', 'content_type', $id);

        return redirect()->route('admin.types')->with('success', "コンテンツタイプ「{$data['name']}」とテーブル「" . ContentSchema::tableName($data['slug']) . '」を作成しました。メニューに表示されています。');
    }

    public function edit(int $id): string
    {
        return $this->form($this->findOrFail($id));
    }

    private function form(?object $item): string
    {
        // After a failed save, restore the fields the user was building.
        $old    = session('_ci_old_input');
        $fields = isset($old['post']['fields_json'])
            ? (json_decode((string) $old['post']['fields_json'], true) ?: [])
            : ($item->fields ?? []);

        return $this->render('admin.types.form', [
            'item'       => $item,
            'fields'     => $fields,
            'savedKeys'  => $this->savedKeys($item->fields ?? []),
            'fieldTypes' => Fields::TYPES,
            'operators'  => Fields::OPERATORS,
            'reserved'   => ['entry' => ContentSchema::BASE_COLUMNS, 'row' => ContentSchema::ROW_COLUMNS],
        ]);
    }

    public function update(int $id): RedirectResponse
    {
        $item = $this->findOrFail($id);
        $data = $this->validated($item);

        if (! is_array($data)) {
            return $data;
        }

        $changes = $this->applySchema((object) $data, $item);
        if (! is_array($changes)) {
            return $changes;
        }

        model(ContentTypeModel::class)->update($item->id, $data);
        $this->rememberSchema();

        $summary = $changes === [] ? '' : '（' . implode('、', $changes) . '）';
        log_activity('type.updated', "コンテンツタイプ「{$data['name']}」を更新しました{$summary}", 'content_type', (int) $item->id);

        return redirect()->route('admin.types')->with('success', "コンテンツタイプ「{$data['name']}」を更新しました。");
    }

    public function delete(int $id): RedirectResponse
    {
        $item  = $this->findOrFail($id);
        $count = EntryModel::for($item)->countAllResults();

        if ($count > 0) {
            return redirect()->route('admin.types')->with('error', "「{$item->name}」には投稿が{$count}件あります。先に投稿を削除してください。");
        }

        // Also removes deleted (trashed) entries and repeater rows.
        service('contentSchema')->drop($item);
        model(ContentTypeModel::class)->delete($item->id);
        $this->rememberSchema();
        log_activity('type.deleted', "コンテンツタイプ「{$item->name}」とそのテーブルを削除しました", 'content_type', (int) $item->id);

        return redirect()->route('admin.types')->with('success', "コンテンツタイプ「{$item->name}」を削除しました。");
    }

    /**
     * Check the new definition against the database and create/alter the tables.
     *
     * @return list<string>|RedirectResponse the changes made, or a redirect back with errors
     */
    private function applySchema(object $type, ?object $old): array|RedirectResponse
    {
        $schema = service('contentSchema');
        $errors = $schema->check($type, $old);

        if ($errors !== []) {
            return $this->backWithErrors($errors);
        }

        $removed = $schema->destructiveChanges($type, $old);
        if ($removed !== [] && $this->request->getPost('confirm_drop') !== '1') {
            return $this->backWithErrors([
                '保存すると次のデータが完全に削除されます：' . implode('、', $removed) . '。続行する場合は、確認のうえもう一度保存してください。',
            ]);
        }

        try {
            return $schema->sync($type, $old);
        } catch (\Throwable $e) {
            log_message('error', 'Content type schema change failed: {message}', ['message' => $e->getMessage()]);

            return $this->backWithErrors(['データベースのテーブルを更新できませんでした：' . $e->getMessage()]);
        }
    }

    /**
     * Record which definitions the tables match (App\Filters\DbUpgrade repairs tables when this differs).
     */
    private function rememberSchema(): void
    {
        model(SettingModel::class)->put('content_schema_hash', ContentSchema::hash(model(ContentTypeModel::class)->allBySlug()));
    }

    /**
     * Keys of fields already saved (the builder asks before deleting their data).
     */
    private function savedKeys(array $fields): array
    {
        $keys = [];
        foreach ($fields as $field) {
            if ($field['type'] !== 'group') {
                $keys[] = $field['key'];
            }
            $keys = [...$keys, ...$this->savedKeys($field['sub_fields'] ?? [])];
        }

        return $keys;
    }

    private function findOrFail(int $id): object
    {
        return model(ContentTypeModel::class)->find($id) ?? throw PageNotFoundException::forPageNotFound();
    }

    /**
     * Validated data, or a redirect back to the form with errors.
     */
    private function validated(?object $existing = null): array|RedirectResponse
    {
        $id    = $existing->id ?? 0;
        $rules = [
            'title_field' => ['label' => 'タイトルに使うフィールド', 'rules' => 'permit_empty|max_length[64]'],
            'name'        => ['label' => '名前', 'rules' => 'required|max_length[100]'],
            'singular'    => ['label' => '単数形の名前', 'rules' => 'permit_empty|max_length[100]'],
            'slug'        => ['label' => 'スラッグ', 'rules' => "required|max_length[50]|regex_match[/^[a-z0-9]+(-[a-z0-9]+)*$/]|is_unique[content_types.slug,id,{$id}]"],
            'icon'        => ['label' => 'アイコン', 'rules' => 'permit_empty|max_length[50]|regex_match[/^[a-z0-9-]+$/]'],
            'description' => ['label' => '説明文', 'rules' => 'permit_empty|max_length[255]'],
            'sort_order'  => ['label' => '表示順', 'rules' => 'permit_empty|integer'],
            'preview_view' => ['label' => 'プレビュー用テンプレート', 'rules' => ['permit_empty', 'max_length[191]', 'regex_match[/^(frontend(\.[a-z0-9_-]+)+|\/[a-z0-9\/_-]*)$/]']],
        ];
        $messages = [
            'slug' => [
                'regex_match' => 'スラッグには半角英小文字・数字・ハイフン（連続不可）のみ使用できます（例：「news」「store-items」）。',
                'is_unique'   => 'このスラッグは他のコンテンツタイプで使用されています。',
            ],
        ];

        $input         = $this->request->getPost(array_keys($rules));
        $input['slug'] = strtolower(trim((string) $input['slug']));

        if (! $this->validateData($input, $rules, $messages)) {
            return $this->backWithErrors($this->validator->getErrors());
        }

        if (in_array($input['slug'], [...config('Cms')->reservedSlugs, config('Cms')->adminPath], true)) {
            return $this->backWithErrors(['slug' => "「{$input['slug']}」は予約語のため使用できません。別のスラッグを指定してください。"]);
        }

        // Preview: a page URL with a public route ("/recruit"), or an existing template under View/frontend/.
        $previewView = trim((string) $input['preview_view']);
        if (str_starts_with($previewView, '/')) {
            if (Entries::pageRoute($previewView) === null) {
                return $this->backWithErrors(['preview_view' => "プレビュー用のページ「{$previewView}」が routes/web.php に見つかりません。"]);
            }
        } elseif ($previewView !== '' && ! is_file(ROOTPATH . 'View/' . str_replace('.', '/', $previewView) . '.blade.php')) {
            return $this->backWithErrors(['preview_view' => "プレビュー用テンプレート「{$previewView}」が見つかりません（View/" . str_replace('.', '/', $previewView) . '.blade.php）。']);
        }

        $errors = [];
        $fields = service('fields')->sanitizeDefinitions(json_decode((string) $this->request->getPost('fields_json'), true), $errors);

        if ($errors !== []) {
            return $this->backWithErrors($errors);
        }

        $candidates = service('fields')->titleCandidates($fields);
        $titleField = isset($candidates[(string) $input['title_field']]) ? (string) $input['title_field'] : array_key_first($candidates);

        return [
            'name'        => trim($input['name']),
            'singular'    => trim((string) $input['singular']) ?: trim($input['name']),
            'slug'        => $input['slug'],
            'icon'        => trim((string) $input['icon']) ?: 'file-earmark-text',
            'description' => trim((string) $input['description']),
            'fields'      => $fields,
            'title_field' => $titleField,
            'sort_order'  => (int) $input['sort_order'],
            'preview_view' => $previewView !== '' ? $previewView : null,
        ];
    }
}
