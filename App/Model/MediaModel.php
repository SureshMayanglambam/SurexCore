<?php

namespace App\Model;

use App\Libraries\ContentSchema;
use CodeIgniter\Model;

/**
 * Media library: files in public/uploads/ (table wd_media).
 */
class MediaModel extends Model
{
    protected $table         = 'media';
    protected $returnType    = 'object';
    protected $allowedFields = ['path', 'original_name', 'mime', 'size', 'width', 'height', 'user_id', 'created_at'];

    /** Tables that never contain file paths, or must not be searched. */
    private const SKIP_TABLES = ['media', 'activity_log', 'migrations', 'users', 'content_types'];

    /**
     * Add a file under public/ to the library (or return the existing row id). Size, type and
     * image dimensions are read from the file itself.
     */
    public function register(string $path, string $originalName, ?int $userId = null, ?string $createdAt = null): int
    {
        $existing = $this->where('path', $path)->first();
        if ($existing !== null) {
            return (int) $existing->id;
        }

        $file = FCPATH . $path;
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($file);
        $size = str_starts_with($mime, 'image/') ? @getimagesize($file) : false;

        return (int) $this->insert([
            'path'          => $path,
            'original_name' => mb_substr($originalName, 0, 255),
            'mime'          => mb_substr($mime, 0, 100),
            'size'          => (int) filesize($file),
            'width'         => $size ? $size[0] : null,
            'height'        => $size ? $size[1] : null,
            'user_id'       => $userId,
            'created_at'    => $createdAt ?? date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Filter by kind ("image" / "file") and file name.
     */
    public function filter(?string $kind, string $search = ''): static
    {
        if ($kind === 'image') {
            $this->like('mime', 'image/', 'after');
        } elseif ($kind === 'file') {
            $this->notLike('mime', 'image/', 'after');
        }
        if ($search !== '') {
            $this->like('original_name', $search);
        }

        return $this->orderBy('created_at', 'DESC')->orderBy('id', 'DESC');
    }

    /**
     * Where a file is used: content types (incl. repeater rows and editor HTML) and site settings.
     *
     * @return list<array{label: string, count: int}>
     */
    public function usages(string $path): array
    {
        $db     = $this->db;
        $prefix = $db->getPrefix();
        $labels = ['settings' => 'サイト設定'];

        foreach (model(ContentTypeModel::class)->allBySlug() as $slug => $type) {
            $labels[ContentSchema::tableName($slug)] = $type->name;
        }

        $found = [];
        foreach ($db->listTables() as $fullName) {
            $table = $prefix !== '' && str_starts_with($fullName, $prefix) ? substr($fullName, strlen($prefix)) : $fullName;
            if (in_array($table, self::SKIP_TABLES, true)) {
                continue;
            }

            $columns = array_filter($db->getFieldData($table), static fn ($c) => preg_match('/char|text|json/i', (string) $c->type));
            if ($columns === []) {
                continue;
            }

            $builder = $db->table($table)->groupStart();
            foreach ($columns as $column) {
                $builder->orLike($column->name, $path);
            }
            $count = $builder->groupEnd()->countAllResults();

            if ($count > 0) {
                // Repeater rows (news__faq) count for their content type (news).
                $label         = $labels[explode('__', $table)[0]] ?? $table;
                $found[$label] = ($found[$label] ?? 0) + $count;
            }
        }

        return array_map(static fn ($label, $count) => ['label' => $label, 'count' => $count], array_keys($found), $found);
    }
}
