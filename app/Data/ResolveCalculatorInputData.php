<?php

namespace App\Data;

use App\Calculators\Graph\CalculatorGraph;
use App\Models\Calculator;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The declared `input:` shape of the `resolve` operation on `calculators`: the widget's current
 * `inputs`, keyed by node id. Beam validates it before the handler runs, so a bad request is a 422.
 *
 *     { "inputs": { "asset": "ETH" } }
 *     { "inputs": { "holdings": [{ "symbol": "BTC", "enabled": true }] } }
 *
 * A value is a scalar or, for a collection node, a list of rows. Inputs left out take their
 * control defaults.
 */
#[Title('Calculator inputs')]
#[TypeScript]
class ResolveCalculatorInputData extends Data
{
    /**
     * @param  array<string, scalar|list<array<string, scalar>>>  $inputs
     */
    public function __construct(
        #[LiteralTypeScriptType('Record<string, number | string | boolean | Array<Record<string, number | string | boolean>>>')]
        public array $inputs = [],
    ) {}

    /**
     * The rules come from the calculator the route names, so a resolve can only carry what that
     * calculator declares: its own input ids, numbers where a control is numeric, an enum or symbol
     * from its options, rows with its declared fields. Beam has already read the calculator
     * through the resource (a draft is a 404) and checked the ability by the time this runs.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(Request $request): array
    {
        $calculator = Calculator::query()->published()->where('slug', $request->route('id'))->first();

        return [
            'inputs' => ['sometimes', 'array'],
            'inputs.*' => [self::scalarOrRows(...)],
            ...($calculator === null ? [] : self::rulesFor(new CalculatorGraph($calculator->graph))),
        ];
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

        return ['inputs' => ['sometimes', 'array:'.implode(',', $ids)], ...$rules];
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
