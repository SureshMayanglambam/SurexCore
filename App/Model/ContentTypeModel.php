<?php

namespace App\Model;

use CodeIgniter\Model;

/**
 * Content types created from the admin panel (Settings → Content Types).
 */
class ContentTypeModel extends Model
{
    protected $table         = 'content_types';
    protected $returnType    = 'object';
    protected $useTimestamps = true;
    protected $allowedFields = ['name', 'singular', 'slug', 'icon', 'description', 'fields', 'title_field', 'preview_view', 'sort_order'];
    protected $beforeInsert  = ['encodeFields'];
    protected $beforeUpdate  = ['encodeFields'];
    protected $afterFind     = ['decodeFields'];
    protected $afterInsert   = ['clearCache'];
    protected $afterUpdate   = ['clearCache'];
    protected $afterDelete   = ['clearCache'];

    private ?array $cache = null;

    /**
     * All types in menu order, keyed by slug. Cached for the request (the sidebar uses it on every page).
     *
     * @return array<string, object>
     */
    public function allBySlug(): array
    {
        if ($this->cache === null) {
            $rows        = $this->orderBy('sort_order')->orderBy('name')->findAll();
            $this->cache = array_column($rows, null, 'slug');
        }

        return $this->cache;
    }

    public function findBySlug(string $slug): ?object
    {
        return $this->allBySlug()[$slug] ?? null;
    }

    /**
     * Field definitions are stored as JSON and used as arrays (see App\Libraries\Fields).
     */
    protected function encodeFields(array $data): array
    {
        if (isset($data['data']['fields']) && is_array($data['data']['fields'])) {
            $data['data']['fields'] = json_encode($data['data']['fields'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $data;
    }

    protected function decodeFields(array $data): array
    {
        $decode = static function (?object $row): ?object {
            if ($row !== null) {
                $row->fields = json_decode((string) ($row->fields ?? ''), true) ?: [];
            }

            return $row;
        };

        if ($data['singleton']) {
            $data['data'] = $decode($data['data']);
        } else {
            $data['data'] = array_map($decode, $data['data'] ?? []);
        }

        return $data;
    }

    protected function clearCache(array $data): array
    {
        $this->cache = null;

        return $data;
    }
}
