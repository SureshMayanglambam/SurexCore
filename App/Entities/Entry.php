<?php

namespace App\Entities;

use App\Model\EntryModel;
use CodeIgniter\Entity\Entity;

/**
 * One row of a content type table. Columns are properties; nested values via field():
 *
 *   {{ $item->title }}                     built-in
 *   {{ $item->price }}                     a field = a column
 *   {{ $item->company_address }}           a field in a group = prefixed column
 *   {{ $item->field('company.address') }}  the same, by path
 *   {!! $item->field('body') !!}           Content (CKEditor) HTML, with this site's URLs restored
 *   <img src="{{ media_url($item->photo) }}">
 *   @foreach($item->faq as $row) {{ $row['question'] }} @endforeach    repeater rows (loaded on first use)
 */
class Entry extends Entity
{
    protected $dates = [];

    protected $casts = [
        'id'        => 'integer',
        'author_id' => '?integer',
    ];

    private ?EntryModel $store = null;
    private ?array $values     = null;

    /**
     * @internal called by EntryModel after loading
     */
    public function setStore(EntryModel $store): static
    {
        $this->store  = $store;
        $this->values = null;

        return $this;
    }

    /**
     * Use these values instead of loading them from the database (entry preview of unsaved input).
     *
     * @internal
     */
    public function setValues(array $values): static
    {
        $this->values = $values;

        return $this;
    }

    /**
     * All custom field values as a nested array (groups as arrays, repeaters as lists of rows).
     */
    public function values(): array
    {
        return $this->values ??= $this->store?->valuesOf($this) ?? [];
    }

    /**
     * A custom field value by name or dot path ("company.address", "faq.0.question").
     */
    public function field(string $path, mixed $default = null): mixed
    {
        $value = $this->values();

        foreach (explode('.', $path) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value ?? $default;
    }

    /**
     * Labels instead of stored values for Select / Radio / Checkbox fields:
     *   {{ $item->label('color') }}                        "Blue" (stored "blue")
     *   {{ implode(', ', $item->label('categories')) }}    checkboxes → list of labels
     */
    public function label(string $path): string|array
    {
        $value   = $this->field($path);
        $choices = array_column($this->store?->fieldDefinition($path)['choices'] ?? [], 'label', 'value');
        $map     = static fn ($v) => $choices[$v] ?? $v;

        return is_array($value) ? array_map($map, $value) : ($value === null || $value === '' ? '' : $map($value));
    }

    /**
     * Linked entries of a relation field (published only, in the order chosen in the admin):
     *   {{ $post->relation('shop')->title }}                 single  → Entry or null
     *   @foreach($post->relation('tags') as $tag) … @endforeach   multiple → list of Entry
     */
    public function relation(string $path): Entry|array|null
    {
        $definition = $this->store?->fieldDefinition($path) ?? [];
        $ids        = array_map('intval', (array) $this->field($path, []));
        $multiple   = ! empty($definition['multiple']);
        $slug       = (string) ($definition['related_type'] ?? '');

        if ($ids === [] || $slug === '' || ! content_type_exists($slug)) {
            return $multiple ? [] : null;
        }

        $found = [];
        foreach (\App\Model\EntryModel::for($slug)->published()->whereIn('id', $ids)->findAll() as $entry) {
            $found[(int) $entry->id] = $entry;
        }
        $ordered = array_values(array_filter(array_map(static fn ($id) => $found[$id] ?? null, $ids)));

        return $multiple ? $ordered : ($ordered[0] ?? null);
    }

    /**
     * Custom field columns come back typed (numbers, booleans, checkbox arrays, editor HTML with
     * this site's URLs). Repeaters and groups are not columns: $item->faq, $item->company.
     */
    public function __get(string $key)
    {
        if ($this->store !== null) {
            $path = $this->store->pathOfColumn($key);

            if ($path !== null) {
                return $this->field(implode('.', $path));
            }
            if (! array_key_exists($key, $this->attributes) && array_key_exists($key, $this->values())) {
                return $this->values()[$key];
            }
        }

        return parent::__get($key);
    }
}
