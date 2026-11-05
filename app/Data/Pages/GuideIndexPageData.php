<?php

namespace App\Data\Pages;

use App\Data\GuideSummaryData;
use Spatie\LaravelData\Data;

/**
 * The props of `guides/index`: the browse cards and the filter values the page echoes back.
 */
class GuideIndexPageData extends Data
{
    /**
     * @param  array<int, GuideSummaryData>  $guides
     * @param  array<string, string>  $filters
     */
    public function __construct(
        public array $guides,
        public array $filters,
    ) {}
}
