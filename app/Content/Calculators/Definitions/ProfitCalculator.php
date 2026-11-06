<?php

declare(strict_types=1);

namespace App\Content\Calculators\Definitions;

use App\Content\Calculators\CalculatorDefinition;

/**
 * The pattern-proving calculator: profit, loss, and ROI against a live, cited market price.
 */
class ProfitCalculator extends CalculatorDefinition
{
    public function slug(): string
    {
        return 'crypto-profit';
    }

    public function name(): string
    {
        return 'Crypto Profit Calculator';
    }

    public function summary(): string
    {
        return 'See your profit, loss, and ROI against the live market price — every figure cited, never stale.';
    }

    public function key(): string
    {
        return 'finance.investment-profit';
    }

    public function category(): string
    {
        return 'profit';
    }

    public function tags(): array
    {
        return ['profit', 'roi', 'live-price'];
    }

    public function primaryGuide(): ?string
    {
        return 'how-crypto-profit-works';
    }

    public function graph(): array
    {
        return [
            'nodes' => [
                ['id' => 'amount', 'kind' => 'input', 'label' => 'Amount held', 'control' => ['type' => 'number', 'default' => 0.5, 'min' => 0, 'step' => 'any', 'unit' => 'coins']],
                ['id' => 'buyPrice', 'kind' => 'input', 'label' => 'Buy price', 'control' => ['type' => 'number', 'default' => 30000, 'min' => 0, 'step' => 'any', 'unit' => 'USD']],
                ['id' => 'asset', 'kind' => 'input', 'label' => 'Asset', 'control' => ['type' => 'enum', 'default' => 'BTC', 'options' => ['BTC', 'ETH', 'SOL']]],
                ['id' => 'sellPrice', 'kind' => 'ref', 'label' => 'Live price', 'format' => 'currency', 'ref' => ['connector' => 'coinmarketcap', 'metric' => 'spot_usd', 'symbolFrom' => 'asset']],
                ['id' => 'cost', 'kind' => 'computed', 'label' => 'Cost basis', 'expr' => 'amount * buyPrice', 'deps' => ['amount', 'buyPrice'], 'format' => 'currency'],
                ['id' => 'value', 'kind' => 'computed', 'label' => 'Current value', 'expr' => 'amount * sellPrice', 'deps' => ['amount', 'sellPrice'], 'format' => 'currency'],
                ['id' => 'profit', 'kind' => 'output', 'label' => 'Profit / loss', 'expr' => 'value - cost', 'deps' => ['value', 'cost'], 'format' => 'currency'],
                ['id' => 'roi', 'kind' => 'output', 'label' => 'ROI', 'expr' => 'cost == 0 ? 0 : (profit / cost) * 100', 'deps' => ['profit', 'cost'], 'format' => 'percent'],
            ],
            'outputs' => ['profit', 'roi'],
            'chart' => [
                'type' => 'bars',
                'title' => 'Cost basis vs current value',
                'series' => [
                    ['id' => 'cost', 'label' => 'Cost'],
                    ['id' => 'value', 'label' => 'Value'],
                ],
            ],
        ];
    }
}
