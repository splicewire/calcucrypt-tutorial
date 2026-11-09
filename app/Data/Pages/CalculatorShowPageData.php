<?php

namespace App\Data\Pages;

use App\Data\CalculatorResolutionData;
use App\Data\CalculatorSpecData;
use App\Data\GuideSummaryData;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;

/**
 * The props of `calculators/show`: the `calculators` resource's own projection, its live
 * resolution, and the Guides that explain it.
 */
class CalculatorShowPageData extends Data
{
    /**
     * @param  array<int, GuideSummaryData>  $guides
     */
    public function __construct(
        public CalculatorSpecData $spec,
        public Lazy|CalculatorResolutionData $resolution,
        public array $guides,
        public ?GuideSummaryData $primaryGuide,
    ) {}
}
