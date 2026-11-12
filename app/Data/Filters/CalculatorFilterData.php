<?php

declare(strict_types=1);

namespace App\Data\Filters;

use Rushing\DataFilters\Attributes\Filterable;
use Rushing\DataFilters\Attributes\Sortable;
use Rushing\DataFilters\Operators\Exact;
use Rushing\DataFilters\Operators\Search;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * The queryable surface of a Calculator — declared once, drives both the browse sidebar
 * (via the generated x-filter schema) and the list query. `filter[q]` searches name,
 * summary, and Calculator Key; the same key powers the "Search the Crypt" box.
 */
class CalculatorFilterData extends Data
{
    public function __construct(
        #[Filterable(Search::class, name: 'q', columns: ['name', 'summary', 'key'])]
        public string|Optional|null $search = null,

        #[Filterable(Exact::class)]
        #[Sortable]
        public string|Optional|null $key = null,

        #[Sortable]
        public string|Optional|null $name = null,
    ) {}
}
