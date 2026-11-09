<?php

namespace App\Data\Pages;

use App\Data\CalculatorSummaryData;
use Spatie\LaravelData\Data;

/**
 * The props of `calculators/index`: the browse cards and the filter values the page echoes back.
 */
class CalculatorIndexPageData extends Data
{
    /**
     * @param  array<int, CalculatorSummaryData>  $calculators
     * @param  array<string, string>  $filters
     */
    public function __construct(
        public array $calculators,
        public array $filters,
    ) {}
}
