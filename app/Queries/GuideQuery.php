<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Guide;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Rushing\DataFilters\Query\ResourceQuery;

/**
 * Binds the Guide filter surface to the Guide search index, scoped to published guides, newest first.
 */
class GuideQuery extends ResourceQuery
{
    protected function baseQuery(Request $request): Builder
    {
        return Guide::query()->published()->with('category');
    }

    protected function defaultSort(): ?string
    {
        return '-published_at';
    }
}
