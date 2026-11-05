<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Calculator;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The full, self-describing shape of a Calculator the frontend needs to render and evaluate
 * it: identity plus the raw `graph` (nodes + outputs + chart hint). The graph is shipped
 * as-is; the client types it via the hand-written CalculatorGraph interface
 * (resources/js/calculators/types.ts), which stays in step with the seed JSON schema.
 */
#[TypeScript]
class CalculatorSpecData extends Data
{
    /**
     * @param  array<string, mixed>  $graph
     */
    public function __construct(
        public string $slug,
        public string $name,
        public ?string $summary,
        public string $computation,
        public ?string $category,
        public array $graph,
    ) {}

    public static function fromModel(Calculator $calculator): self
    {
        return new self(
            slug: $calculator->slug,
            name: $calculator->name,
            summary: $calculator->summary,
            computation: $calculator->computation,
            category: $calculator->category?->name,
            graph: $calculator->graph,
        );
    }
}
