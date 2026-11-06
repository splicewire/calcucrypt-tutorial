<?php

declare(strict_types=1);

namespace App\Content\Calculators\Definitions;

use App\Content\Calculators\CalculatorDefinition;

/**
 * Crypto-to-crypto conversion at live prices: how much of coin B a given amount of coin A is
 * worth right now, plus the effective exchange rate. Two live-price refs, one per coin.
 */
class ConverterCalculator extends CalculatorDefinition
{
    public function slug(): string
    {
        return 'crypto-converter';
    }

    public function name(): string
    {
        return 'Crypto Converter';
    }

    public function summary(): string
    {
        return 'Convert one coin into another at live prices, with the effective exchange rate between them.';
    }

    public function computation(): string
    {
        return 'converter';
    }

    public function category(): string
    {
        return 'converter';
    }

    public function primaryGuide(): ?string
    {
        return 'conversions-and-units';
    }

    public function graph(): array
    {
        $assets = ['BTC', 'ETH', 'SOL', 'BNB', 'XRP', 'ADA', 'AVAX', 'DOGE'];

        return [
            'nodes' => [
                ['id' => 'amount', 'kind' => 'input', 'label' => 'Amount', 'control' => ['type' => 'number', 'default' => 1, 'min' => 0, 'step' => 'any', 'unit' => 'coins']],
                ['id' => 'fromCoin', 'kind' => 'input', 'label' => 'From', 'control' => ['type' => 'enum', 'default' => 'BTC', 'options' => $assets]],
                ['id' => 'toCoin', 'kind' => 'input', 'label' => 'To', 'control' => ['type' => 'enum', 'default' => 'ETH', 'options' => $assets]],
                ['id' => 'fromPrice', 'kind' => 'ref', 'label' => 'From price', 'format' => 'currency', 'ref' => ['connector' => 'coinmarketcap', 'metric' => 'spot_usd', 'symbolFrom' => 'fromCoin']],
                ['id' => 'toPrice', 'kind' => 'ref', 'label' => 'To price', 'format' => 'currency', 'ref' => ['connector' => 'coinmarketcap', 'metric' => 'spot_usd', 'symbolFrom' => 'toCoin']],
                ['id' => 'converted', 'kind' => 'output', 'label' => 'Converted amount', 'expr' => 'toPrice == 0 ? 0 : (amount * fromPrice) / toPrice', 'deps' => ['amount', 'fromPrice', 'toPrice'], 'format' => 'number'],
                ['id' => 'rate', 'kind' => 'output', 'label' => 'Exchange rate', 'expr' => 'toPrice == 0 ? 0 : fromPrice / toPrice', 'deps' => ['fromPrice', 'toPrice'], 'format' => 'number'],
            ],
            'outputs' => ['converted', 'rate'],
        ];
    }
}
