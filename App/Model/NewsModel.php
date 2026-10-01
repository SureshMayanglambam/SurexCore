<?php

namespace App\Model;

use App\Entities\Entry;

/**
 * SAMPLE MODEL — お知らせ (content type "news", table {prefix}news).
 *
 * A model per content type, like a Laravel model per table. Create the content type in the admin first:
 *   設定 → コンテンツタイプ → name お知らせ, slug news, fields:
 *     headline (テキスト, used as the entry title)
 *     body     (本文 / CKEditor)
 *
 * Then use it in controllers:
 *   model(NewsModel::class)->latest(3)
 *   model(NewsModel::class)->where('status', 'published')->orderBy('published_at', 'DESC')->paginate(10)
 */
class NewsModel extends EntryModel
{
    protected string $contentTypeSlug = 'news';

    /**
     * Latest published news, newest first (e.g. for the top page).
     *
     * @return list<Entry>
     */
    public function latest(int $limit = 3): array
    {
        return $this->published()
            ->latestPublished()
            ->findAll($limit);
    }

    /**
     * One page of published news, newest first. The pager is then in $this->pager.
     *
     * @return list<Entry>
     */
    public function paginated(int $perPage = 10): array
    {
        return $this->published()
            ->latestPublished()
            ->paginate($perPage);
    }

    /**
     * A published news item by its slug, or null (drafts and scheduled posts are hidden).
     */
    public function findBySlug(string $slug): ?Entry
    {
        return $this->findPublished($slug);
    }
}
