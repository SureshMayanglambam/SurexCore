<?php

namespace App\Model;

/**
 * お知らせ (content type "news"). Create it with: php spark db:seed NewsSample
 *
 *   NewsModel::published()->latest()->paginate(10);
 *   NewsModel::published()->where('slug', $slug)->firstOrFail();
 */
class NewsModel extends EntryModel
{
    protected string $contentTypeSlug = 'news';
}
