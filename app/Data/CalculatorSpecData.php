<?php

namespace App\Data;

use App\Models\Calculator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
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
 *
 * A paid calculator the reader's plan does not cover is **locked**: it is still listed and
 * described, but its `graph` is null, so the math never leaves the server. The page, the API and
 * a Guide's embed all read this one projection, so none of them can show more than it does.
 */
#[ParticleResource(
    key: 'calculators',
    backing: Calculator::class,
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
     * @param  array<string, mixed>|null  $graph  null when the calculator is locked
     */
    public function __construct(
        public string $slug,
        public string $name,
        public ?string $summary,
        public string $key,
        public ?string $category,
        public ?array $graph,
        public bool $locked,
        public ?string $requiredPlan,
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

    /**
     * The reader is whoever is signed in; a guest is null. `CalculatorPolicy::resolve()` decides
     * who may use the calculator, so seeing the graph and resolving it follow one rule.
     */
    public static function project(Calculator $calculator): self
    {
        $locked = Gate::denies('resolve', $calculator);

        return new self(
            slug: $calculator->slug,
            name: $calculator->name,
            summary: $calculator->summary,
            key: $calculator->key,
            category: $calculator->category?->name,
            graph: $locked ? null : $calculator->graph,
            locked: $locked,
            requiredPlan: $calculator->requiredPlan(),
        );
    }
}
