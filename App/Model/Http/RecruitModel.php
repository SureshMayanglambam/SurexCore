<?php

namespace App\Model\Http;

use App\Model\EntryModel;
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
