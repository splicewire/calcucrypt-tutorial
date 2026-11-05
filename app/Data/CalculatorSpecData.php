<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Calculator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Splicewire\Beam\Particle\Attributes\ParticleResource;

/**
 * The full, self-describing shape of a Calculator the frontend needs to render and evaluate
 * it: identity plus the raw `graph` (nodes + outputs + chart hint). The graph is shipped
 * as-is; the client types it via the hand-written CalculatorGraph interface
 * (resources/js/calculators/types.ts), which stays in step with the seed JSON schema.
 *
 * It is also where the `calculators` particle resource is declared: the read projection of
 * every published Calculator, addressed by slug, readable by anyone and writable by no one.
 * `routes/api.php` mounts it; the tool page renders the same projection.
 *
 * A paid calculator the reader's plan does not cover is **locked**: it is still listed and
 * described, but its `graph` is null, so the math never leaves the server. The page, the API and
 * a Guide's embed all read this one projection, so none of them can show more than it does.
 */
#[ParticleResource(
    key: 'calculators',
    backing: Calculator::class,
    data: CalculatorSpecData::class,
    input: false,
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
        public string $computation,
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
     * The reader is whoever is signed in, read through the `Auth` facade as Beam's `scope`
     * convention does; a guest is null. `CalculatorPolicy::resolve()` decides who may use the
     * calculator, so seeing the graph and resolving it follow one rule.
     */
    public static function project(Calculator $calculator): self
    {
        $locked = Gate::denies('resolve', $calculator);

        return new self(
            slug: $calculator->slug,
            name: $calculator->name,
            summary: $calculator->summary,
            computation: $calculator->computation,
            category: $calculator->category?->name,
            graph: $locked ? null : $calculator->graph,
            locked: $locked,
            requiredPlan: $calculator->requiredPlan(),
        );
    }
}
