<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One embedded Calculator inside a Guide: where it sits (`anchor`) plus its full spec and a
 * server-resolved grounding. Carrying the resolution here means the embed boots with the
 * same live prices and provenance as the standalone page — no separate client fetch, no
 * lesser copy. A locked calculator carries no resolution, and the Guide renders it as a
 * locked card.
 */
#[TypeScript]
class CalculatorEmbedData extends Data
{
    public function __construct(
        public ?string $anchor,
        public CalculatorSpecData $spec,
        public ?CalculatorResolutionData $resolution,
    ) {}
}
