import { describe, expect, it } from 'vitest';
import { computeValues } from './graph';
import type { CalculatorGraph } from './types';

const positionSize: CalculatorGraph = {
    nodes: [
        { id: 'account', kind: 'input', label: 'Account' },
        { id: 'riskPct', kind: 'input', label: 'Risk %' },
        { id: 'entry', kind: 'input', label: 'Entry' },
        { id: 'stop', kind: 'input', label: 'Stop' },
        { id: 'target', kind: 'input', label: 'Target' },
        { id: 'spot', kind: 'ref', label: 'Live', ref: { connector: 'coinmarketcap', metric: 'spot_usd', symbolFrom: 'asset' } },
        { id: 'riskUsd', kind: 'computed', label: 'Risk budget', expr: 'account * riskPct / 100', deps: ['account', 'riskPct'] },
        { id: 'perUnitRisk', kind: 'computed', label: 'Risk per unit', expr: 'abs(entry - stop)', deps: ['entry', 'stop'] },
        { id: 'size', kind: 'output', label: 'Size', expr: 'perUnitRisk == 0 ? 0 : riskUsd / perUnitRisk', deps: ['riskUsd', 'perUnitRisk'] },
        { id: 'rr', kind: 'output', label: 'R:R', expr: 'perUnitRisk == 0 ? 0 : abs(target - entry) / perUnitRisk', deps: ['target', 'entry', 'perUnitRisk'] },
    ],
    outputs: ['size', 'rr'],
};

const leverage: CalculatorGraph = {
    nodes: [
        { id: 'margin', kind: 'input', label: 'Margin' },
        { id: 'leverage', kind: 'input', label: 'Leverage' },
        { id: 'maintPct', kind: 'input', label: 'Maintenance %' },
        { id: 'entry', kind: 'ref', label: 'Entry', ref: { connector: 'coinmarketcap', metric: 'spot_usd', symbolFrom: 'asset' } },
        { id: 'positionValue', kind: 'computed', label: 'Position value', expr: 'margin * leverage', deps: ['margin', 'leverage'] },
        { id: 'liquidation', kind: 'output', label: 'Liquidation', expr: 'leverage == 0 ? 0 : entry * (1 - 1 / leverage + maintPct / 100)', deps: ['entry', 'leverage', 'maintPct'] },
    ],
    outputs: ['liquidation'],
};

const cryptoVsStocks: CalculatorGraph = {
    nodes: [
        { id: 'coinChange', kind: 'ref', label: 'Coin 24h', ref: { connector: 'coinmarketcap', metric: 'change_pct', symbolFrom: 'coin' } },
        { id: 'marketChange', kind: 'ref', label: 'Market today', ref: { connector: 'finnhub', metric: 'change_pct', symbolFrom: 'ticker' } },
        { id: 'spread', kind: 'output', label: 'Outperformance', expr: 'coinChange - marketChange', deps: ['coinChange', 'marketChange'] },
    ],
    outputs: ['spread'],
};

describe('trader calculators (client engine)', () => {
    it('sizes a position to the risk budget and computes R:R', () => {
        const values = computeValues(positionSize, { account: 25000, riskPct: 1, entry: 30000, stop: 28500, target: 33000, spot: 30000 });
        expect(values.riskUsd).toBe(250);
        expect(values.perUnitRisk).toBe(1500);
        expect(values.size).toBeCloseTo(250 / 1500, 9);
        expect(values.rr).toBeCloseTo(3000 / 1500, 9); // reward 3000, risk 1500 => 2R
    });

    it('guards position size against a zero stop distance', () => {
        const values = computeValues(positionSize, { account: 25000, riskPct: 1, entry: 30000, stop: 30000, target: 33000, spot: 30000 });
        expect(values.size).toBe(0);
        expect(values.rr).toBe(0);
    });

    it('computes a 5x isolated-long liquidation price off the live entry', () => {
        const values = computeValues(leverage, { margin: 1000, leverage: 5, maintPct: 0.5, entry: 100000 });
        // 100000 * (1 - 0.2 + 0.005) = 80500
        expect(values.liquidation).toBeCloseTo(80500, 6);
    });

    it('spreads a coin 24h move against a market move as percentage points', () => {
        const values = computeValues(cryptoVsStocks, { coinChange: 2.1, marketChange: 0.4 });
        expect(values.spread).toBeCloseTo(1.7, 9);
    });
});
