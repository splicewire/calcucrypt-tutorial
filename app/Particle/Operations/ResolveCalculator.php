<?php

namespace App\Particle\Operations;

use App\Calculators\CalculatorResolver;
use App\Data\CalculatorResolutionData;
use App\Data\ResolveCalculatorInputData;
use App\Models\Calculator;
use Splicewire\Beam\Particle\Attributes\ParticleOp;
use Splicewire\Beam\Particle\OperationCall;
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
 *   - `input:` validates the request against the calculator's own inputs (its `rules()` sees the
 *     calculator as the operation's subject), so a bad one is a 422;
 *   - the handler gets the call: the subject in `$call->context`, the validated DTO in `$call->input`.
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
    public static function handle(OperationCall $call): CalculatorResolutionData
    {
        /** @var Calculator $calculator */
        $calculator = $call->context->subject;
        /** @var ResolveCalculatorInputData $input */
        $input = $call->input;

        return app(CalculatorResolver::class)->resolve($calculator, $input->inputs);
    }
}
