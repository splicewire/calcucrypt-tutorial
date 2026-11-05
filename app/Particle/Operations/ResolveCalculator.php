<?php

namespace App\Particle\Operations;

use App\Calculators\CalculatorResolver;
use App\Data\CalculatorResolutionData;
use App\Data\CalculatorResolveInputData;
use App\Models\Calculator;
use Illuminate\Http\Request;
use Splicewire\Beam\Particle\Attributes\ParticleOp;
use Splicewire\Beam\Particle\OperationKind;

/**
 * `POST /api/calculators/{slug}/resolve`: ground a Calculator's live refs for a set of inputs.
 *
 * The widget calls this when an input that steers a ref changes (the asset, or the symbols in a
 * portfolio). The server prices the refs; the browser then evaluates the rest of the graph itself
 * (ADR-0003). It is a Read that takes a body, because a portfolio's rows do not survive a query
 * string intact.
 *
 * Beam does the plumbing before `handle()` runs:
 *
 *   - the `{id}` slug is read through the `calculators` resource, so a draft is a 404;
 *   - `ability: 'resolve'` asks `CalculatorPolicy::resolve()`, guests included;
 *   - `input:` validates the request's shape, so a malformed `inputs` map is a 422.
 *
 * `handle()` then checks the inputs against this calculator's own graph and hands them to the same
 * resolver the tool page uses for its first paint.
 */
#[ParticleOp(
    resource: 'calculators',
    name: 'resolve',
    kind: OperationKind::Read,
    ability: 'resolve',
    affordance: false,
    input: CalculatorResolveInputData::class,
    output: CalculatorResolutionData::class,
)]
class ResolveCalculator
{
    public static function handle(Calculator $calculator, Request $request, mixed $actor): CalculatorResolutionData
    {
        $input = CalculatorResolveInputData::from($request->all())->validateFor($calculator);

        return app(CalculatorResolver::class)->resolve($calculator, $input->inputs);
    }
}
