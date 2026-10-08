<?php

namespace App\Model;

/**
 * Recruit (content type "recruit").
 *
 *   RecruitModel::published()->latest()->paginate(10);
 *   RecruitModel::published()->where('slug', $slug)->firstOrFail();
 */
class RecruitModel extends EntryModel
{
    protected string $contentTypeSlug = 'recruit';
}
