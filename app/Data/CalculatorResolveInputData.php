<?php

namespace App\Data;

use App\Calculators\Graph\CalculatorGraph;
use App\Models\Calculator;
use Closure;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What the `resolve` operation accepts: the widget's current `inputs`, keyed by node id.
 *
 * A value is a scalar (an input such as `asset` or `amount`) or, for a collection node, a list of
 * rows. Inputs left out take their control defaults.
 *
 *     { "inputs": { "asset": "ETH" } }
 *     { "inputs": { "holdings": [{ "symbol": "BTC", "enabled": true }] } }
 *
 * Validation happens in two steps:
 *
 *   - {@see rules()} is the request's shape, the same for every calculator. Beam checks it before
 *     the operation runs.
 *   - {@see validateFor()} checks the inputs against one calculator's graph: only its own input ids,
 *     numbers where a control is numeric, an enum or symbol from its options, rows with its declared
 *     fields. The operation calls it once it has the calculator.
 *
 * Both steps answer a bad request with a 422.
 */
#[Title('Calculator inputs')]
#[TypeScript]
class CalculatorResolveInputData extends Data
{
    /**
     * @param  array<string, scalar|list<array<string, scalar>>>  $inputs
     */
    public function __construct(
        #[LiteralTypeScriptType('Record<string, number | string | boolean | Array<Record<string, number | string | boolean>>>')]
        public array $inputs = [],
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'inputs' => ['sometimes', 'array'],
            'inputs.*' => [self::scalarOrRows(...)],
        ];
    }

    /**
     * Check the inputs against the calculator's graph, so a resolve can only carry what that
     * calculator declares.
     */
    public function validateFor(Calculator $calculator): static
    {
        Validator::make(['inputs' => $this->inputs], self::rulesFor(new CalculatorGraph($calculator->graph)))
            ->validate();

        return $this;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private static function rulesFor(CalculatorGraph $graph): array
    {
        $ids = [];
        $rules = [];

        foreach ($graph->nodes() as $id => $node) {
            $control = $node['control'] ?? null;

            if ($control === null || ! in_array($node['kind'] ?? null, ['input', 'collection'], true)) {
                continue;
            }

            $ids[] = $id;

            if ($control['type'] !== 'collection') {
                $rules["inputs.{$id}"] = ['sometimes', ...self::controlRules($control)];

                continue;
            }

            $fields = array_column($control['fields'], null, 'id');
            $rules["inputs.{$id}"] = ['sometimes', 'list', 'max:'.($control['maxRows'] ?? 50)];
            $rules["inputs.{$id}.*"] = ['array:'.implode(',', array_keys($fields))];

            foreach ($fields as $fieldId => $field) {
                $rules["inputs.{$id}.*.{$fieldId}"] = ['sometimes', ...self::controlRules($field)];
            }
        }

        return ['inputs' => ['array:'.implode(',', $ids)], ...$rules];
    }

    /**
     * The rules one control (or one collection field) puts on its value.
     *
     * @param  array<string, mixed>  $control
     * @return array<int, mixed>
     */
    private static function controlRules(array $control): array
    {
        return match ($control['type'] ?? null) {
            'number' => ['numeric'],
            'toggle' => ['boolean'],
            'enum', 'symbol' => isset($control['options'])
                ? ['string', Rule::in($control['options'])]
                : ['string'],
            default => [],
        };
    }

    /**
     * A value is a scalar or a list of rows, and a row holds only scalars.
     */
    private static function scalarOrRows(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_scalar($value)) {
            return;
        }

        $isRow = fn (mixed $row): bool => is_array($row)
            && array_filter($row, fn (mixed $cell): bool => ! is_scalar($cell)) === [];

        if (! is_array($value) || ! array_is_list($value) || array_filter($value, $isRow) !== $value) {
            $fail("The {$attribute} field must be a number, a string, or a list of rows.");
        }
    }
}
