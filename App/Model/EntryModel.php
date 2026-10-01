<?php

namespace App\Model;

use App\Entities\Entry;
use App\Libraries\ContentSchema;
use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Model;
use CodeIgniter\Validation\ValidationInterface;
use RuntimeException;

/**
 * Model for one content type's own table (e.g. "store"), like a Laravel model per table.
 * Created per type — there is no fixed table:
 *
 *   EntryModel::for('store')->where('price <', 1000)->orderBy('price')->findAll();
 *
 * In frontend controllers use the helpers: entries('store'), entry('store', $slug).
 */
class EntryModel extends Model
{
    protected $returnType     = Entry::class;
    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;
    protected $afterFind      = ['attachType'];

    private const BASE_FIELDS = ['title', 'slug', 'status', 'author_id', 'meta_title', 'meta_description', 'published_at'];

    /**
     * Unsaved entries being previewed, per content type slug (Admin\Entries::preview()).
     *
     * @var array<string, Entry>
     */
    private static array $previews = [];

    /**
     * Bind a model class to a content type (like a Laravel model to its table):
     *
     *   class NewsModel extends EntryModel
     *   {
     *       protected string $contentTypeSlug = 'news';
     *   }
     *
     *   model(NewsModel::class)->published()->findAll();
     */
    protected string $contentTypeSlug = '';

    private object $contentType;
    private array $layout;

    public function __construct(?ConnectionInterface $db = null, ?ValidationInterface $validation = null)
    {
        parent::__construct($db, $validation);

        if ($this->contentTypeSlug !== '') {
            $this->useContentType($this->contentTypeSlug);
        }
    }

    /**
     * @param object|string $type content type row or slug
     */
    public static function for(object|string $type): static
    {
        $model = new static();
        $model->useContentType($type);

        return $model;
    }

    /**
     * Point this model at a content type's table and columns.
     */
    private function useContentType(object|string $type): void
    {
        if (is_string($type)) {
            $slug = $type;
            $type = model(ContentTypeModel::class)->findBySlug($slug)
                ?? throw new RuntimeException("Unknown content type \"{$slug}\". Create it under Settings → Content Types.");
        }

        $this->contentType   = $type;
        $this->layout        = service('contentSchema')->layout($type);
        $this->allowedFields = [...self::BASE_FIELDS, ...array_column($this->layout['columns'], 'column')];
        $this->setTable($this->layout['table']);
    }

    public function contentType(): object
    {
        return $this->contentType;
    }

    /**
     * Unprefixed table name, for qualifying columns in queries ("store.price").
     */
    public function tableName(): string
    {
        return $this->table;
    }

    /**
     * Only content visitors may see: published and not scheduled in the future.
     */
    public function published(): static
    {
        $this->where($this->table . '.status', 'published')
            ->where($this->table . '.published_at <=', date('Y-m-d H:i:s'));

        return $this;
    }

    public function findPublished(string $slug): ?Entry
    {
        return $this->published()->where($this->table . '.slug', $slug)->first();
    }

    /**
     * Newest first by publish date.
     */
    public function latestPublished(): static
    {
        $this->orderBy($this->table . '.published_at', 'DESC');

        return $this;
    }

    public function withAuthor(): static
    {
        $this->select($this->table . '.*, users.name AS author_name')
            ->join('users', 'users.id = ' . $this->table . '.author_id', 'left');

        return $this;
    }

    /**
     * Make a slug unique within the table by appending -2, -3, ...
     */
    public function uniqueSlug(string $slug, ?int $ignoreId = null): string
    {
        $candidate = $slug;

        for ($i = 2; ; $i++) {
            $query = $this->withDeleted()->where('slug', $candidate);

            if ($ignoreId !== null) {
                $query->where('id !=', $ignoreId);
            }
            if ($query->countAllResults() === 0) {
                return $candidate;
            }

            $candidate = $slug . '-' . $i;
        }
    }

    /**
     * Insert or update an entry: built-in columns + nested custom field values
     * (repeater rows are written to their own tables). Returns the entry id.
     */
    public function saveEntry(array $base, array $values, ?int $id = null): int
    {
        $schema = service('contentSchema');
        $row    = array_intersect_key($base, array_flip(self::BASE_FIELDS)) + $schema->toRow($this->layout, $values);

        $this->db->transException(true)->transStart();

        if ($id === null) {
            $id = (int) $this->insert($row);
        } else {
            $this->update($id, $row);
        }
        $schema->saveChildren($this->layout, $id, $values);

        $this->db->transComplete();

        return $id;
    }

    /**
     * Nested custom field values of an entry (for forms and Entry::field()).
     */
    public function valuesOf(Entry $entry): array
    {
        return service('contentSchema')->values($this->layout, $entry->toRawArray());
    }

    /**
     * Field path of a custom field column ("company_address" → ["company", "address"]), or null.
     */
    public function pathOfColumn(string $column): ?array
    {
        foreach ($this->layout['columns'] as $col) {
            if ($col['column'] === $column) {
                return $col['path'];
            }
        }

        return null;
    }

    /**
     * Field definition by name or dot path ("company.address"; repeater rows use "faq.question").
     */
    public function fieldDefinition(string $path): ?array
    {
        $fields = $this->contentType->fields ?? [];
        $field  = null;

        foreach (explode('.', $path) as $segment) {
            if (ctype_digit($segment)) {
                continue;   // repeater row index
            }
            $field = null;
            foreach ($fields as $candidate) {
                if ($candidate['name'] === $segment) {
                    $field = $candidate;
                    break;
                }
            }
            if ($field === null) {
                return null;
            }
            $fields = $field['sub_fields'] ?? [];
        }

        return $field;
    }

    /**
     * Preview: while rendering a page, lists of this content type show $entry (unsaved form values)
     * in place of its saved version — or in addition, when it is new or a draft.
     */
    public static function previewWith(string $typeSlug, Entry $entry): void
    {
        self::$previews[$typeSlug] = $entry;
    }

    /**
     * Lets entities read nested values and repeaters (see Entry::field()).
     */
    protected function attachType(array $data): array
    {
        $attach = fn ($entry) => $entry instanceof Entry ? $entry->setStore($this) : $entry;

        $data['data'] = $data['singleton'] ? $attach($data['data']) : array_map($attach, $data['data'] ?? []);

        return $this->withPreview($data);
    }

    /**
     * Swap in (or add) the entry being previewed. Only entity results; counts and arrays are left alone.
     */
    private function withPreview(array $data): array
    {
        $preview = self::$previews[$this->contentType->slug ?? ''] ?? null;

        if ($preview === null) {
            return $data;
        }

        if ($data['singleton']) {
            if ($data['data'] instanceof Entry && (int) $data['data']->id === (int) $preview->id) {
                $data['data'] = $preview;
            }

            return $data;
        }

        $rows = $data['data'] ?? [];
        if ($rows !== [] && ! $rows[array_key_first($rows)] instanceof Entry) {
            return $data;
        }

        $found = false;
        foreach ($rows as $i => $row) {
            if ((int) $row->id === (int) $preview->id) {
                $rows[$i] = $preview;
                $found    = true;
            }
        }
        if (! $found) {
            $rows[] = $preview;   // new or unpublished entry: shown after the others
        }
        $data['data'] = array_values($rows);

        return $data;
    }
}
