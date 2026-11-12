<?php

namespace App\Data\Pages;

use App\Data\CalculatorEmbedData;
use App\Data\CalculatorSummaryData;
use App\Data\GuideData;
use Spatie\LaravelData\Data;

/**
 * The props of `guides/show`: the `guides` resource's own projection, the calculators it embeds
 * (each server-resolved), and the ones it links to.
 */
class GuideShowPageData extends Data
{
    /**
     * @param  array<int, CalculatorEmbedData>  $embeds
     * @param  array<int, CalculatorSummaryData>  $links
     */
    public function __construct(
        public GuideData $guide,
        public array $embeds,
        public array $links,
    ) {}
}
