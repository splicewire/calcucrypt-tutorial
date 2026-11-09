<?php

namespace App\Data;

use Illuminate\Validation\Rule;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The declared `input:` shape of the `purchase` operation on `plans`: which plan to buy. Beam
 * validates it before the handler runs, so a plan that is not on sale is a 422.
 */
#[Title('Plan purchase')]
#[TypeScript]
class PurchasePlanInputData extends Data
{
    public function __construct(
        public string $plan,
    ) {}

    /** @return array<string, array<int, mixed>> */
    public static function rules(): array
    {
        return [
            'plan' => ['required', 'string', Rule::in(array_keys((array) config('plans', [])))],
        ];
    }
}
