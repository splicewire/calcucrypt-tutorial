<?php

declare(strict_types=1);

namespace App\Content\Calculators\Definitions;

use App\Content\Calculators\CalculatorDefinition;

/**
 * Capital gains with short- vs long-term treatment decided by holding days: gain, the rate
 * that applies, the tax owed, and net proceeds. No live ref — pure inputs.
 */
class CapitalGainsCalculator extends CalculatorDefinition
{
    public function slug(): string
    {
        return 'crypto-capital-gains';
    }

    public function name(): string
    {
        return 'Capital Gains Calculator';
    }

    public function summary(): string
    {
        return 'Estimate tax owed and net proceeds, with short- or long-term treatment set by how long you held.';
    }

    public function computation(): string
    {
        return 'capital-gains';
    }

    public function category(): string
    {
        return 'tax';
    }

    public function entitlement(): ?string
    {
        return 'calculators.'.$this->slug();
    }

    public function primaryGuide(): ?string
    {
        return 'crypto-taxes-end-to-end';
    }

    public function graph(): array
    {
        return [
            'nodes' => [
                ['id' => 'proceeds', 'kind' => 'input', 'label' => 'Sale proceeds', 'control' => ['type' => 'number', 'default' => 12000, 'min' => 0, 'step' => 'any', 'unit' => 'USD']],
                ['id' => 'costBasis', 'kind' => 'input', 'label' => 'Cost basis', 'control' => ['type' => 'number', 'default' => 8000, 'min' => 0, 'step' => 'any', 'unit' => 'USD']],
                ['id' => 'holdingDays', 'kind' => 'input', 'label' => 'Days held', 'control' => ['type' => 'number', 'default' => 400, 'min' => 0, 'step' => 1, 'unit' => 'days']],
                ['id' => 'shortRate', 'kind' => 'input', 'label' => 'Short-term rate', 'control' => ['type' => 'number', 'default' => 24, 'min' => 0, 'step' => 'any', 'unit' => '%']],
                ['id' => 'longRate', 'kind' => 'input', 'label' => 'Long-term rate', 'control' => ['type' => 'number', 'default' => 15, 'min' => 0, 'step' => 'any', 'unit' => '%']],
                ['id' => 'gain', 'kind' => 'computed', 'label' => 'Gain', 'expr' => 'proceeds - costBasis', 'deps' => ['proceeds', 'costBasis'], 'format' => 'currency'],
                ['id' => 'isLong', 'kind' => 'computed', 'label' => 'Long-term?', 'expr' => 'holdingDays >= 365 ? 1 : 0', 'deps' => ['holdingDays']],
                ['id' => 'rate', 'kind' => 'computed', 'label' => 'Applied rate', 'expr' => 'isLong == 1 ? longRate : shortRate', 'deps' => ['isLong', 'longRate', 'shortRate'], 'format' => 'percent'],
                ['id' => 'taxOwed', 'kind' => 'output', 'label' => 'Tax owed', 'expr' => 'gain > 0 ? gain * rate / 100 : 0', 'deps' => ['gain', 'rate'], 'format' => 'currency'],
                ['id' => 'netProceeds', 'kind' => 'output', 'label' => 'Net proceeds', 'expr' => 'proceeds - taxOwed', 'deps' => ['proceeds', 'taxOwed'], 'format' => 'currency'],
            ],
            'outputs' => ['gain', 'taxOwed', 'netProceeds'],
        ];
    }
}
