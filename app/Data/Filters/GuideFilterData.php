<?php

declare(strict_types=1);

namespace App\Data\Filters;

use Rushing\DataFilters\Attributes\Filterable;
use Rushing\DataFilters\Attributes\Sortable;
use Rushing\DataFilters\Operators\Search;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * The queryable surface of a Guide (Post). `filter[q]` searches title, teaser, and the
 * cached body — the same key the Calculator resource and the "Search the Crypt" box use,
 * so one query value spans the whole library.
 */
class GuideFilterData extends Data
{
    public function __construct(
        #[Filterable(Search::class, name: 'q', columns: ['title', 'teaser', 'body_blocks_html'])]
        public string|Optional|null $search = null,

        #[Sortable]
        public string|Optional|null $title = null,
    ) {}
}
