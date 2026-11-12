<?php

declare(strict_types=1);

namespace App\Data\Filters;

use Rushing\DataFilters\Attributes\Filterable;
use Rushing\DataFilters\Attributes\Sortable;
use Rushing\DataFilters\Operators\Search;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * The queryable surface of a Guide index. `filter[q]` searches title, teaser, and the
 * published source text — the same key the Calculator resource and the "Search the Crypt" box use,
 * so one query value spans the whole library.
 */
class GuideFilterData extends Data
{
    public function __construct(
        #[Filterable(Search::class, name: 'q', columns: ['title', 'teaser', 'search_body'])]
        public string|Optional|null $search = null,

        #[Sortable]
        public string|Optional|null $title = null,
    ) {}
}
