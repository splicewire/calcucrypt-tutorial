<?php

namespace App\Data;

use Closure;
use Illuminate\Http\Request;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The write DTO for the `saved-inputs` particle resource: the `input:` slot of SavedInputData.
 *
 *     { "calculator": "crypto-profit", "name": "My usual ETH trade", "inputs": { "asset": "ETH", "amount": 2 } }
 *
 * A save names its calculator by slug; an update may leave it out and change only the name or the inputs.
 */
#[Title('Saved inputs')]
#[TypeScript]
class SavedInputInputData extends Data
{
    /**
     * @param  array<string, int|float|string>|null  $inputs
     */
    public function __construct(
        public ?string $calculator = null,
        public ?string $name = null,
        #[LiteralTypeScriptType('Record<string, number | string> | null')]
        public ?array $inputs = null,
    ) {}

    /**
     * A save (POST) needs all three; an update needs none of them.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(Request $request): array
    {
        $presence = $request->isMethod('post') ? 'required' : 'sometimes';

        return [
            'calculator' => [$presence, 'string', 'exists:calculators,slug'],
            'name' => [$presence, 'string', 'max:80'],
            'inputs' => [$presence, 'array'],
            'inputs.*' => [static function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_scalar($value)) {
                    $fail("The {$attribute} field must be a number or a string.");
                }
            }],
        ];
    }

    /**
     * The write map: DTO field ⇒ model column. The owner and the calculator are set in SavedInputData::prepare().
     *
     * @return array<string, mixed>
     */
    public function toModelAttributes(): array
    {
        return array_filter([
            'name' => $this->name,
            'inputs' => $this->inputs,
        ], fn (mixed $value): bool => $value !== null);
    }
}
