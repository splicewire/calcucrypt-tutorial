<?php

namespace App\Data\Pages;

use App\Data\CalculatorResolutionData;
use App\Data\CalculatorSpecData;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;

/**
 * The props of `calculators/show`: the `calculators` resource's own projection and its live
 * resolution.
 */
class CalculatorShowPageData extends Data
{
    public function __construct(
        public CalculatorSpecData $spec,
        public Lazy|CalculatorResolutionData $resolution,
    ) {}
}
