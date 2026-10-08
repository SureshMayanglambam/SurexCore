<?php

namespace App\Model;

use App\Entities\Entry;
use App\Libraries\ContentSchema;
use CodeIgniter\Exceptions\PageNotFoundException;
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

    private const BASE_FIELDS = ['title', 'slug', 'status', 'author_id', 'meta_title', 'meta_description', 'published_at', 'published_until'];

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
            $type = model(ContentTypeModel::class)->findBySlug($slug);

            if ($type === null) {
                // Live site: a page whose content type doesn't exist (yet) is simply not found.
                if (ENVIRONMENT === 'production') {
                    throw PageNotFoundException::forPageNotFound();
                }

                throw new RuntimeException("Unknown content type \"{$slug}\". Create it under 設定 → コンテンツタイプ"
                    . ($slug === 'news' ? ' (sample: php spark db:seed NewsSample).' : '.'));
            }
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
     * Only content visitors may see: status 公開, the publish date reached, and the end date
     * (公開終了日時) not reached yet — an empty end date means no end.
     */
    protected function scopePublished(): void
    {
        $now = date('Y-m-d H:i:s');

        $this->where($this->table . '.status', 'published')
            ->where($this->table . '.published_at <=', $now)
            ->groupStart()
                ->where($this->table . '.published_until', null)
                ->orWhere($this->table . '.published_until >', $now)
            ->groupEnd();
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

    // ------------------------------------------------------------------
    // Laravel-style queries. Static calls start a new query, like Laravel models:
    //
    //   NewsModel::published()->latest()->paginate(10);
    //   NewsModel::published()->where('slug', $slug)->firstOrFail();
    //   NewsModel::published()->where('news_type', 'event')->latest()->limit(3)->get();
    //   NewsModel::published()->whereJsonContains('news_category', 'company')->get();
    //
    // Scopes (published, latest, oldest, whereJsonContains) are methods named scopeXxx;
    // every CodeIgniter query method works too: where, orWhere, whereIn, like, orderBy, first, ...
    // ------------------------------------------------------------------

    /**
     * NewsModel::published() → (new NewsModel())->published()
     */
    public static function __callStatic(string $name, array $params): mixed
    {
        return (new static())->{$name}(...$params);
    }

    /**
     * Scopes first, then CodeIgniter's query builder.
     */
    public function __call(string $name, array $params)
    {
        $scope = 'scope' . ucfirst($name);
        if (method_exists($this, $scope)) {
            $this->{$scope}(...$params);

            return $this;
        }

        return parent::__call($name, $params);
    }

    /**
     * A new, empty query: NewsModel::query()->where(...)->get()
     */
    public static function query(): static
    {
        return new static();
    }

    /** Newest first (default: by publish date). */
    protected function scopeLatest(string $column = 'published_at'): void
    {
        $this->orderBy($this->table . '.' . $column, 'DESC');
    }

    /** Oldest first (default: by publish date). */
    protected function scopeOldest(string $column = 'published_at'): void
    {
        $this->orderBy($this->table . '.' . $column, 'ASC');
    }

    /** Checkbox fields hold a JSON list: entries where this value is checked. */
    protected function scopeWhereJsonContains(string $column, string|int $value): void
    {
        $this->like($this->table . '.' . $column, '"' . $value . '"');
    }

    /** limit() / offset() for get() — findAll() would replace a limit set on the builder. */
    private ?int $queryLimit = null;
    private int $queryOffset = 0;

    public function limit(?int $limit = null, ?int $offset = 0): static
    {
        $this->queryLimit  = $limit;
        $this->queryOffset = (int) $offset;

        return $this;
    }

    public function offset(int $offset): static
    {
        $this->queryOffset = $offset;

        return $this;
    }

    /**
     * Run the query: the matching entries, with limit() / offset() if set.
     *
     * @return list<Entry>
     */
    public function get(): array
    {
        [$limit, $offset]  = [$this->queryLimit ?? 0, $this->queryOffset];
        $this->queryLimit  = null;
        $this->queryOffset = 0;

        return $this->findAll($limit, $offset);
    }

    /**
     * The first match, or the 404 page.
     */
    public function firstOrFail(): Entry
    {
        return $this->first() ?? throw PageNotFoundException::forPageNotFound();
    }

    /**
     * An entry by id, or the 404 page.
     */
    public function findOrFail(int $id): Entry
    {
        return $this->find($id) ?? throw PageNotFoundException::forPageNotFound();
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
