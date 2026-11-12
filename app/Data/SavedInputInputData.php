<?php

namespace App\Data;

use App\Models\Calculator;
use Closure;
use Illuminate\Http\Request;
use Schemastud\DataSchemas\Attributes\MapValues;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** Omitted fields preserve existing values; these business fields cannot be cleared with null. */
#[Title('Saved inputs')]
#[TypeScript]
class SavedInputInputData extends Data
{
    /** @param array<string, int|float|string>|Optional $inputs */
    public function __construct(
        public string|Optional $calculator = new Optional,
        public string|Optional $name = new Optional,
        #[MapValues(['number', 'string'])]
        #[\Spatie\TypeScriptTransformer\Attributes\Optional]
        #[LiteralTypeScriptType('Record<string, number | string>')]
        public array|Optional $inputs = new Optional,
    ) {}

    /** @return array<string, array<int, mixed>> */
    public static function rules(Request $request): array
    {
        $presence = $request->isMethod('post') ? 'required' : 'sometimes';

        return [
            'calculator' => [$presence, 'string', static function (string $attribute, mixed $value, Closure $fail): void {
                if (! Calculator::query()->published()->where('slug', $value)->exists()) {
                    $fail('Choose a published calculator.');
                }
            }],
            'name' => [$presence, 'string', 'max:80'],
            'inputs' => [$presence, 'array', static function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_array($value) || ($value !== [] && array_is_list($value))) {
                    $fail('Inputs must be a named set of numbers or strings.');

                    return;
                }
                foreach ($value as $item) {
                    if ((! is_int($item) && ! is_float($item) && ! is_string($item))
                        || (is_float($item) && ! is_finite($item))) {
                        $fail('Each input must be a finite number or a string.');

                        return;
                    }
                }
            }],
        ];
    }
}
