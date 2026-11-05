<?php

declare(strict_types=1);

namespace App\Content\Calculators\Definitions;

use App\Content\Calculators\CalculatorDefinition;

/**
 * The trader's daily driver: how many units to buy so a losing trade costs only the fraction
 * of the account you're willing to risk — plus the risk/reward of the setup. The live spot is
 * cited so you can see how far your planned entry sits from the current market.
 */
class PositionSizeCalculator extends CalculatorDefinition
{
    public function slug(): string
    {
        return 'crypto-position-size';
    }

    public function name(): string
    {
        return 'Position Size & Risk/Reward Calculator';
    }

    public function summary(): string
    {
        return 'Size a trade to a fixed % of your account and see its risk/reward against your stop and target.';
    }

    public function computation(): string
    {
        return 'position-size';
    }

    public function category(): string
    {
        return 'trader';
    }

    public function primaryGuide(): ?string
    {
        return 'trade-like-a-risk-manager';
    }

    public function graph(): array
    {
        return [
            'nodes' => [
                ['id' => 'account', 'kind' => 'input', 'label' => 'Account size', 'control' => ['type' => 'number', 'default' => 25000, 'min' => 0, 'step' => 'any', 'unit' => 'USD']],
                ['id' => 'riskPct', 'kind' => 'input', 'label' => 'Risk per trade', 'control' => ['type' => 'number', 'default' => 1, 'min' => 0, 'step' => 'any', 'unit' => '%']],
                ['id' => 'asset', 'kind' => 'input', 'label' => 'Asset', 'control' => ['type' => 'enum', 'default' => 'BTC', 'options' => ['BTC', 'ETH', 'SOL', 'BNB', 'XRP', 'ADA', 'AVAX', 'DOGE']]],
                ['id' => 'entry', 'kind' => 'input', 'label' => 'Entry price', 'control' => ['type' => 'number', 'default' => 30000, 'min' => 0, 'step' => 'any', 'unit' => 'USD']],
                ['id' => 'stop', 'kind' => 'input', 'label' => 'Stop-loss', 'control' => ['type' => 'number', 'default' => 28500, 'min' => 0, 'step' => 'any', 'unit' => 'USD']],
                ['id' => 'target', 'kind' => 'input', 'label' => 'Target', 'control' => ['type' => 'number', 'default' => 33000, 'min' => 0, 'step' => 'any', 'unit' => 'USD']],
                ['id' => 'spot', 'kind' => 'ref', 'label' => 'Live price', 'format' => 'currency', 'ref' => ['connector' => 'coinmarketcap', 'metric' => 'spot_usd', 'symbolFrom' => 'asset']],
                ['id' => 'riskUsd', 'kind' => 'computed', 'label' => 'Risk budget', 'expr' => 'account * riskPct / 100', 'deps' => ['account', 'riskPct'], 'format' => 'currency'],
                ['id' => 'perUnitRisk', 'kind' => 'computed', 'label' => 'Risk per unit', 'expr' => 'abs(entry - stop)', 'deps' => ['entry', 'stop'], 'format' => 'currency'],
                ['id' => 'size', 'kind' => 'output', 'label' => 'Position size', 'expr' => 'perUnitRisk == 0 ? 0 : riskUsd / perUnitRisk', 'deps' => ['riskUsd', 'perUnitRisk'], 'format' => 'number'],
                ['id' => 'positionValue', 'kind' => 'output', 'label' => 'Position value', 'expr' => 'size * entry', 'deps' => ['size', 'entry'], 'format' => 'currency'],
                ['id' => 'rr', 'kind' => 'output', 'label' => 'Risk / reward', 'expr' => 'perUnitRisk == 0 ? 0 : abs(target - entry) / perUnitRisk', 'deps' => ['target', 'entry', 'perUnitRisk'], 'format' => 'number'],
                ['id' => 'pctFromLive', 'kind' => 'output', 'label' => 'Entry vs live price', 'expr' => 'spot == 0 ? 0 : ((entry - spot) / spot) * 100', 'deps' => ['entry', 'spot'], 'format' => 'percent'],
            ],
            'outputs' => ['size', 'riskUsd', 'rr'],
            'chart' => [
                'type' => 'bars',
                'title' => 'Risk vs reward per unit',
                'series' => [
                    ['id' => 'perUnitRisk', 'label' => 'Risk'],
                    ['id' => 'rr', 'label' => 'R multiple'],
                ],
            ],
        ];
    }
}
