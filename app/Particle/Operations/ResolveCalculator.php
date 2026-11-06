<?php

namespace App\Particle\Operations;

use App\Calculators\CalculatorResolver;
use App\Data\CalculatorResolutionData;
use App\Data\ResolveCalculatorInputData;
use App\Models\Calculator;
use Illuminate\Http\Request;
use Splicewire\Beam\Particle\Attributes\ParticleOp;
use Splicewire\Beam\Particle\OperationKind;

/**
 * The `resolve` operation on the `calculators` particle resource: ground a calculator's live refs
 * for a set of inputs. The widget calls it when an input that steers a ref changes (the asset, or
 * the symbols in a portfolio). The server prices the refs; the browser evaluates the rest.
 *
 * Beam does the plumbing before `handle()` runs:
 *
 *   - the `{id}` slug is read through the `calculators` resource, so a draft is a 404;
 *   - `ability: 'resolve'` asks `CalculatorPolicy::resolve()`, guests included;
 *   - `input:` validates the request against the calculator's own inputs, so a bad one is a 422.
 *
 * It is a Read that takes a body, because a portfolio's rows do not survive a query string intact.
 */
#[ParticleOp(
    resource: 'calculators',
    name: 'resolve',
    summary: 'Price a calculator\'s live refs for a set of inputs',
    description: null,
    kind: OperationKind::Read,
    ability: 'resolve',
    affordance: false,
    input: ResolveCalculatorInputData::class,
    output: CalculatorResolutionData::class,
)]
class ResolveCalculator
{
    public static function handle(Calculator $model, Request $request, mixed $actor): CalculatorResolutionData
    {
        $input = ResolveCalculatorInputData::from($request->all());

        return app(CalculatorResolver::class)->resolve($model, $input->inputs);
    }
}
