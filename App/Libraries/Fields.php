<?php

namespace App\Libraries;

/**
 * Custom field definitions (like ACF) and the values stored for them.
 *
 * A definition is an array:
 *   key, label, name, type, instructions, required, show_in_list,
 *   plus type settings: placeholder, default, rows, min, max, step, choices [{value,label}],
 *   sub_fields (group / repeater), min_rows, max_rows, button_label,
 *   conditions  – show the field only when these rules match,
 *   required_if – the field is required only when these rules match.
 *
 * Rules (ACF-style) are OR-groups of AND-rules referring to sibling fields by key:
 *   [[{field: "f_abc123", operator: "==", value: "red"}, {...}], [...]]
 */
class Fields
{
    /**
     * Available field types: type => [label, category].
     */
    public const TYPES = [
        'text'     => ['テキスト', '基本'],
        'textarea' => ['テキストエリア', '基本'],
        'editor'   => ['本文（CKEditor）', '基本'],
        'number'   => ['数値', '基本'],
        'email'    => ['メールアドレス', '基本'],
        'url'      => ['URL', '基本'],
        'date'     => ['日付', '基本'],
        'image'    => ['画像', 'メディア'],
        'file'     => ['ファイル', 'メディア'],
        'select'   => ['セレクト', '選択'],
        'radio'    => ['ラジオボタン', '選択'],
        'checkbox' => ['チェックボックス', '選択'],
        'toggle'   => ['オン／オフ', '選択'],
        'relation' => ['関連付け（他のコンテンツ）', '選択'],
        'group'    => ['グループ', 'レイアウト'],
        'repeater' => ['リピーター', 'レイアウト'],
    ];

    /** Types that can hold sub fields. */
    public const CONTAINERS = ['group', 'repeater'];

    /** Types with a list of choices. */
    public const CHOICE_TYPES = ['select', 'radio', 'checkbox'];

    /** Groups/repeaters may be nested this deep. */
    public const MAX_DEPTH = 3;

    /** Condition operators. */
    public const OPERATORS = [
        '=='        => 'と等しい',
        '!='        => 'と等しくない',
        'contains'  => 'を含む',
        '>'         => 'より大きい',
        '<'         => 'より小さい',
        'empty'     => 'が未入力',
        'not_empty' => 'が入力済み',
    ];

    // ------------------------------------------------------------------
    // Definitions (content type settings)
    // ------------------------------------------------------------------

    /**
     * Clean field definitions posted by the field builder.
     *
     * @param list<string> $errors collects problems, e.g. "Field 3: name is required"
     */
    public function sanitizeDefinitions(mixed $fields, array &$errors, string $path = '', int $depth = 1): array
    {
        if (! is_array($fields)) {
            return [];
        }

        $clean  = [];
        $names  = [];
        $raws   = [];
        $places = [];

        foreach (array_values($fields) as $i => $field) {
            if (! is_array($field)) {
                continue;
            }

            $label = trim((string) ($field['label'] ?? ''));
            $name  = trim((string) ($field['name'] ?? ''));
            $type  = (string) ($field['type'] ?? 'text');
            $where = $path . ($label !== '' ? "「{$label}」" : 'フィールド' . ($i + 1));

            if ($label === '') {
                $errors[] = "{$where}：ラベルは必須です。";
            }
            if (! preg_match('/^[a-z][a-z0-9_]{0,63}$/', $name)) {
                $errors[] = "{$where}：名前は英字で始め、半角英小文字・数字・アンダースコアのみで入力してください（例: price、main_image）。";
            } elseif (isset($names[$name])) {
                $errors[] = "{$where}：名前「{$name}」が同じ階層で重複しています。";
            }
            if (! isset(self::TYPES[$type])) {
                $errors[] = "{$where}：不明なフィールドタイプです。";
                $type = 'text';
            }
            if (in_array($type, self::CONTAINERS, true) && $depth >= self::MAX_DEPTH) {
                $errors[] = "{$where}：グループとリピーターの入れ子は" . self::MAX_DEPTH . '階層までです。';
            }

            $names[$name] = true;

            $def = [
                'key'          => preg_match('/^f_[a-z0-9]{6,16}$/', (string) ($field['key'] ?? '')) ? $field['key'] : 'f_' . bin2hex(random_bytes(5)),
                'label'        => mb_substr($label, 0, 100),
                'name'         => $name,
                'type'         => $type,
                'instructions' => mb_substr(trim((string) ($field['instructions'] ?? '')), 0, 500),
                'required'     => ! empty($field['required']),
                // A column in the admin entry list (top-level fields only, not groups/repeaters).
                'show_in_list' => $depth === 1 && ! in_array($type, self::CONTAINERS, true) && ! empty($field['show_in_list']),
            ];

            switch ($type) {
                case 'text':
                case 'email':
                case 'url':
                    $def['placeholder'] = mb_substr(trim((string) ($field['placeholder'] ?? '')), 0, 191);
                    $def['default']     = mb_substr((string) ($field['default'] ?? ''), 0, 1000);
                    break;

                case 'textarea':
                    $def['placeholder'] = mb_substr(trim((string) ($field['placeholder'] ?? '')), 0, 191);
                    $def['default']     = (string) ($field['default'] ?? '');
                    $def['rows']        = max(2, min(30, (int) ($field['rows'] ?? 4)));
                    break;

                case 'number':
                    $def['placeholder'] = mb_substr(trim((string) ($field['placeholder'] ?? '')), 0, 191);
                    $def['default']     = $this->numberOrNull($field['default'] ?? null);
                    $def['min']         = $this->numberOrNull($field['min'] ?? null);
                    $def['max']         = $this->numberOrNull($field['max'] ?? null);
                    $def['step']        = $this->numberOrNull($field['step'] ?? null);
                    break;

                case 'select':
                case 'radio':
                case 'checkbox':
                    $def['choices'] = $this->parseChoices($field['choices'] ?? []);
                    $def['default'] = trim((string) ($field['default'] ?? ''));
                    if ($def['choices'] === []) {
                        $errors[] = "{$where}：選択肢を1つ以上追加してください。";
                    }
                    break;

                case 'toggle':
                    $def['default'] = ! empty($field['default']);
                    break;

                case 'relation':
                    // Entries of another content type (by slug); one or several.
                    $def['related_type'] = preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', (string) ($field['related_type'] ?? '')) ? $field['related_type'] : '';
                    $def['multiple']     = ! empty($field['multiple']);
                    if ($def['related_type'] === '') {
                        $errors[] = "{$where}：関連付けるコンテンツタイプを選択してください。";
                    }
                    break;

                case 'group':
                case 'repeater':
                    $def['sub_fields'] = $this->sanitizeDefinitions($field['sub_fields'] ?? [], $errors, $where . ' → ', $depth + 1);
                    if ($def['sub_fields'] === []) {
                        $errors[] = "{$where}：サブフィールドを1つ以上追加してください。";
                    }
                    if ($type === 'repeater') {
                        $def['min_rows']     = max(0, (int) ($field['min_rows'] ?? 0));
                        $def['max_rows']     = max(0, (int) ($field['max_rows'] ?? 0));
                        $def['button_label'] = mb_substr(trim((string) ($field['button_label'] ?? '')), 0, 50) ?: '行を追加';
                    }
                    break;
            }

            $clean[]  = $def;
            $raws[]   = $field;
            $places[] = $where;
        }

        // Rules can only point at sibling fields (same level), which are all known now.
        $controllers = [];
        foreach ($clean as $def) {
            if (! in_array($def['type'], self::CONTAINERS, true)) {
                $controllers[$def['key']] = $def;
            }
        }

        foreach ($clean as $i => &$def) {
            $def['conditions']  = $this->sanitizeRules($raws[$i]['conditions'] ?? [], $controllers, $def['key'], $errors, "{$places[$i]}：表示条件のルール");
            $def['required_if'] = $def['required']
                ? []
                : $this->sanitizeRules($raws[$i]['required_if'] ?? [], $controllers, $def['key'], $errors, "{$places[$i]}：必須条件のルール");
        }
        unset($def);

        return $clean;
    }

    /**
     * @param array<string, array> $controllers sibling definitions by key
     */
    private function sanitizeRules(mixed $groups, array $controllers, string $selfKey, array &$errors, string $where): array
    {
        $clean = [];

        foreach (is_array($groups) ? $groups : [] as $group) {
            $rules = [];

            foreach (is_array($group) ? $group : [] as $rule) {
                $key      = (string) ($rule['field'] ?? '');
                $operator = (string) ($rule['operator'] ?? '==');
                $value    = mb_substr(trim((string) ($rule['value'] ?? '')), 0, 191);

                if ($key === '') {
                    $errors[] = "{$where}でフィールドが選択されていません。";

                    continue;
                }
                if ($key === $selfKey || ! isset($controllers[$key])) {
                    $errors[] = "{$where}が同じ階層にないフィールド（または削除されたフィールド）を参照しています。";

                    continue;
                }
                if (! isset(self::OPERATORS[$operator])) {
                    $errors[] = "{$where}の比較方法が不明です。";

                    continue;
                }
                if (in_array($operator, ['>', '<'], true) && ! is_numeric($value)) {
                    $errors[] = "{$where}の比較値「{$value}」が数値ではありません。";

                    continue;
                }

                $rules[] = ['field' => $key, 'operator' => $operator, 'value' => $value];
            }

            if ($rules !== []) {
                $clean[] = $rules;
            }
        }

        return $clean;
    }

    /**
     * Choices come either as [{value,label}] or as text lines "value : Label".
     *
     * @return list<array{value: string, label: string}>
     */
    public function parseChoices(mixed $choices): array
    {
        if (is_string($choices)) {
            $lines   = preg_split('/\R/', $choices) ?: [];
            $choices = [];

            foreach ($lines as $line) {
                if (trim($line) === '') {
                    continue;
                }
                $parts     = explode(':', $line, 2);
                $choices[] = ['value' => trim($parts[0]), 'label' => trim($parts[1] ?? $parts[0])];
            }
        }

        $clean = [];
        $seen  = [];

        foreach ((array) $choices as $choice) {
            $value = mb_substr(trim((string) ($choice['value'] ?? '')), 0, 191);
            $label = mb_substr(trim((string) ($choice['label'] ?? '')), 0, 191);

            if ($value === '' || isset($seen[$value])) {
                continue;
            }
            $seen[$value] = true;
            $clean[]      = ['value' => $value, 'label' => $label !== '' ? $label : $value];
        }

        return $clean;
    }

    /**
     * Top-level text fields that can serve as the entry title.
     *
     * @return array<string, string> name => label
     */
    public function titleCandidates(array $fields): array
    {
        $out = [];

        foreach ($fields as $field) {
            if ($field['type'] === 'text') {
                $out[$field['name']] = $field['label'];
            }
        }

        return $out;
    }

    // ------------------------------------------------------------------
    // Values (entries)
    // ------------------------------------------------------------------

    /**
     * Validate and normalise posted values against the definitions.
     *
     * @param list<string> $errors collects messages like "FAQ row 2 → Question is required."
     */
    public function sanitizeValues(array $fields, mixed $input, array &$errors, string $path = ''): array
    {
        $input   = is_array($input) ? $input : [];
        $visible = $this->visibility($fields, $input);
        $out     = [];

        foreach ($fields as $field) {
            // Hidden by its conditions: not validated, saved empty (like ACF).
            if (! $visible[$field['key']]) {
                $out[$field['name']] = $this->emptyValue($field);

                continue;
            }

            $required = ! empty($field['required'])
                || (! empty($field['required_if']) && $this->rulesMatch($field['required_if'], $fields, $input, $visible));

            $out[$field['name']] = $this->sanitizeValue($field, $input[$field['name']] ?? null, $required, $errors, $path . $field['label']);
        }

        return $out;
    }

    /**
     * Which fields at one level are visible, given the submitted values.
     * Repeated until stable, because a field can depend on a field that is itself conditional.
     *
     * @return array<string, bool> key => visible
     */
    public function visibility(array $fields, array $input): array
    {
        $visible = array_fill_keys(array_column($fields, 'key'), true);

        for ($pass = 0; $pass <= count($fields); $pass++) {
            $changed = false;

            foreach ($fields as $field) {
                if (empty($field['conditions'])) {
                    continue;
                }
                $show = $this->rulesMatch($field['conditions'], $fields, $input, $visible);

                if ($show !== $visible[$field['key']]) {
                    $visible[$field['key']] = $show;
                    $changed                = true;
                }
            }

            if (! $changed) {
                break;
            }
        }

        return $visible;
    }

    /**
     * True when any rule group matches (all rules in a group must match).
     */
    private function rulesMatch(array $groups, array $fields, array $input, array $visible): bool
    {
        $byKey = array_column($fields, null, 'key');

        foreach ($groups as $group) {
            foreach ($group as $rule) {
                $target = $byKey[$rule['field']] ?? null;
                $value  = $target !== null && $visible[$target['key']]
                    ? $this->comparable($target, $input[$target['name']] ?? null)
                    : null;

                if (! $this->compare($rule['operator'], $value, $rule['value'])) {
                    continue 2;
                }
            }

            return true;
        }

        return false;
    }

    /**
     * A submitted value in the form rules compare against: string, or list of strings for checkboxes.
     */
    private function comparable(array $field, mixed $raw): string|array|null
    {
        return match ($field['type']) {
            'checkbox', 'relation' => array_values(array_map('strval', array_filter((array) $raw, static fn ($v) => is_scalar($v) && $v !== ''))),
            'toggle'   => in_array($raw, ['1', 1, true, 'on'], true) ? '1' : '0',
            default    => is_scalar($raw) ? trim((string) $raw) : '',
        };
    }

    private function compare(string $operator, string|array|null $value, string $expected): bool
    {
        $empty = $value === null || $value === '' || $value === [];

        return match ($operator) {
            'empty'     => $empty,
            'not_empty' => ! $empty,
            '=='        => is_array($value) ? in_array($expected, $value, true) : (string) $value === $expected,
            '!='        => is_array($value) ? ! in_array($expected, $value, true) : (string) $value !== $expected,
            'contains'  => is_array($value)
                ? in_array($expected, $value, true)
                : $expected !== '' && mb_stripos((string) $value, $expected) !== false,
            '>'         => ! $empty && is_numeric($value) && is_numeric($expected) && $value + 0 > $expected + 0,
            '<'         => ! $empty && is_numeric($value) && is_numeric($expected) && $value + 0 < $expected + 0,
            default     => false,
        };
    }

    private function emptyValue(array $field): mixed
    {
        return match ($field['type']) {
            'checkbox', 'repeater', 'relation' => [],
            'group'                => array_map(fn ($sub) => $this->emptyValue($sub), array_column($field['sub_fields'], null, 'name')),
            'toggle'               => false,
            'number'               => null,
            default                => '',
        };
    }

    private function sanitizeValue(array $field, mixed $raw, bool $required, array &$errors, string $where): mixed
    {
        switch ($field['type']) {
            case 'text':
            case 'textarea':
            case 'editor':
            case 'email':
            case 'url':
            case 'date':
                $value = is_scalar($raw) ? trim((string) $raw) : '';
                $empty = $field['type'] === 'editor' ? trim(strip_tags($value, '<img><iframe>')) === '' : $value === '';

                if ($empty) {
                    if ($required) {
                        $errors[] = "{$where}は必須です。";
                    }

                    return '';
                }
                if ($field['type'] === 'email' && ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "{$where}は正しいメールアドレスを入力してください。";
                }
                if ($field['type'] === 'url' && ! preg_match('#^(https?://|/|mailto:|tel:)#i', $value)) {
                    $errors[] = "{$where}はhttps://、http://、または / で始まるURLを入力してください。";
                }
                if ($field['type'] === 'date' && ! $this->isDate($value)) {
                    $errors[] = "{$where}は正しい日付を入力してください。";
                }
                // Stored in VARCHAR(255) columns (see ContentSchema).
                if (in_array($field['type'], ['text', 'email'], true) && mb_strlen($value) > 255) {
                    $errors[] = "{$where}は255文字以内で入力してください。長い文章にはテキストエリアのフィールドを使用してください。";
                }

                return $value;

            case 'number':
                if ($raw === null || $raw === '') {
                    if ($required) {
                        $errors[] = "{$where}は必須です。";
                    }

                    return null;
                }
                if (! is_numeric($raw)) {
                    $errors[] = "{$where}は数値で入力してください。";

                    return null;
                }
                $number = $raw + 0;
                if ($field['min'] !== null && $number < $field['min']) {
                    $errors[] = "{$where}は{$field['min']}以上で入力してください。";
                }
                if ($field['max'] !== null && $number > $field['max']) {
                    $errors[] = "{$where}は{$field['max']}以下で入力してください。";
                }

                return $number;

            case 'select':
            case 'radio':
                $allowed = array_column($field['choices'], 'value');
                $value   = is_scalar($raw) ? (string) $raw : '';

                if ($value === '' || ! in_array($value, $allowed, true)) {
                    if ($required) {
                        $errors[] = "{$where}を選択してください。";
                    }

                    return '';
                }

                return $value;

            case 'checkbox':
                $allowed = array_column($field['choices'], 'value');
                $values  = array_values(array_intersect($allowed, array_map('strval', array_filter((array) $raw, 'is_scalar'))));

                if ($values === [] && $required) {
                    $errors[] = "{$where}を1つ以上選択してください。";
                }

                return $values;

            case 'toggle':
                return in_array($raw, ['1', 1, true, 'on'], true);

            case 'relation':
                // Ids of existing entries of the related type, as strings (stored as a JSON list).
                $ids = array_values(array_unique(array_filter(array_map('intval', array_filter((array) $raw, 'is_scalar')), static fn ($id) => $id > 0)));
                $ids = array_values(array_intersect(array_map('strval', $ids), array_map('strval', array_keys($this->relationOptions($field['related_type'])))));
                if (! $field['multiple']) {
                    $ids = array_slice($ids, 0, 1);
                }
                if ($ids === [] && $required) {
                    $errors[] = "{$where}を選択してください。";
                }

                return $ids;

            case 'image':
            case 'file':
                $value = is_string($raw) ? trim($raw) : '';

                if ($value === '') {
                    if ($required) {
                        $errors[] = "{$where}は必須です。";
                    }

                    return '';
                }
                // Only files uploaded through the admin (public/uploads/...) are accepted.
                if (! preg_match('#^uploads/[0-9]{4}/[0-9]{2}/[a-z0-9]+\.[a-z0-9]+$#', $value) || ! is_file(FCPATH . $value)) {
                    $errors[] = "{$where}：アップロードされたファイルが見つかりません。もう一度アップロードしてください。";

                    return '';
                }

                return $value;

            case 'group':
                return $this->sanitizeValues($field['sub_fields'], $raw, $errors, $where . ' → ');

            case 'repeater':
                $rows = array_values(array_filter(is_array($raw) ? $raw : [], 'is_array'));
                $out  = [];

                foreach ($rows as $i => $row) {
                    $out[] = $this->sanitizeValues($field['sub_fields'], $row, $errors, $where . ' ' . ($i + 1) . '行目 → ');
                }

                $count = count($out);
                if ($required && $count === 0) {
                    $errors[] = "{$where}は1行以上必要です。";
                }
                if ($field['min_rows'] > 0 && $count < $field['min_rows']) {
                    $errors[] = "{$where}は最低{$field['min_rows']}行が必要です。";
                }
                if ($field['max_rows'] > 0 && $count > $field['max_rows']) {
                    $errors[] = "{$where}は{$field['max_rows']}行までです。";
                }

                return $out;
        }

        return null;
    }

    /**
     * The value a new entry starts with.
     */
    public function defaultValue(array $field): mixed
    {
        return match ($field['type']) {
            'group'    => $this->defaults($field['sub_fields']),
            'repeater', 'relation' => [],
            'checkbox' => ($field['default'] ?? '') !== '' ? [$field['default']] : [],
            default    => $field['default'] ?? null,
        };
    }

    public function defaults(array $fields): array
    {
        $out = [];

        foreach ($fields as $field) {
            $out[$field['name']] = $this->defaultValue($field);
        }

        return $out;
    }

    /**
     * Entries a relation field can link to: id => title (drafts included, trashed entries not).
     *
     * @return array<int, string>
     */
    public function relationOptions(string $slug): array
    {
        static $cache = [];

        if (! isset($cache[$slug])) {
            $cache[$slug] = $slug !== '' && content_type_exists($slug)
                ? array_column(\App\Model\EntryModel::for($slug)->asArray()->select('id, title')->orderBy('title')->findAll(), 'title', 'id')
                : [];
        }

        return $cache[$slug];
    }

    private function numberOrNull(mixed $value): int|float|null
    {
        return is_numeric($value) ? $value + 0 : null;
    }

    private function isDate(string $value): bool
    {
        $date = \DateTime::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
