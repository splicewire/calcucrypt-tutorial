<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Calculator;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The card shape for a Calculator in browse/search/featured lists — identity without the
 * heavy graph. `locked` says whether the reader's plan covers it (see CalculatorPolicy::resolve),
 * and `requiredPlan` names the plan that would.
 */
#[TypeScript]
class CalculatorSummaryData extends Data
{
    public function __construct(
        public string $slug,
        public string $name,
        public ?string $summary,
        public string $computation,
        public ?string $category,
        public bool $locked,
        public ?string $requiredPlan,
    ) {}

    public static function fromModel(Calculator $calculator): self
    {
        return new self(
            slug: $calculator->slug,
            name: $calculator->name,
            summary: $calculator->summary,
            computation: $calculator->computation,
            category: $calculator->category?->name,
            locked: Gate::denies('resolve', $calculator),
            requiredPlan: $calculator->requiredPlan(),
        );
    }
}
