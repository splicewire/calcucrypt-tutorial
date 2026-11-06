<?php

namespace App\Data;

use App\Models\Calculator;
use Illuminate\Database\Eloquent\Builder;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Splicewire\Beam\Particle\Attributes\ParticleResource;

/**
 * The read projection for the `calculators` particle resource, and its declaration site: every
 * published Calculator, addressed by slug, readable by anyone and writable by no one.
 *
 * `graph` is the calculator's raw node graph (inputs, refs, outputs, chart hint). The browser
 * evaluates it; the client types it through the engine's CalculatorGraph interface
 * (resources/js/calculators/types.ts).
 *
 * The attribute declares; the host routes. `routes/api.php` mounts it, and the tool page renders
 * the same projection.
 */
#[ParticleResource(
    key: 'calculators',
    backing: Calculator::class,
    data: CalculatorSpecData::class,
    input: false,
    // A guest reads this same class, so `project()` runs for a guest too.
    publicRead: CalculatorSpecData::class,
    includes: ['category'],
    readOnly: true,
    routeKey: 'slug',
)]
#[Title('Calculator')]
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

    /**
     * Only live calculators exist on this resource: a draft or expired one is a 404.
     *
     * @param  Builder<Calculator>  $query
     * @return Builder<Calculator>
     */
    public static function scope(Builder $query): Builder
    {
        return $query->published();
    }

    public static function project(Calculator $calculator): self
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
