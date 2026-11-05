<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Post;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Rushing\DataFilters\Query\ResourceQuery;

/**
 * Binds the Guide filter surface to the Post model, scoped to published guides, newest first.
 */
class GuideQuery extends ResourceQuery
{
    protected function baseQuery(Request $request): Builder
    {
        return Post::query()->published()->with('category');
    }

    protected function defaultSort(): ?string
    {
        return '-published_at';
    }
}
