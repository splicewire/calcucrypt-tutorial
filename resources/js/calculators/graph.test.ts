import { describe, expect, it } from 'vitest';
import { computeValues, evaluateGraph, topoOrder } from './graph';
import type { CalculatorGraph } from './types';

const profitGraph: CalculatorGraph = {
    nodes: [
        { id: 'amount', kind: 'input', label: 'Amount' },
        { id: 'buyPrice', kind: 'input', label: 'Buy price' },
        { id: 'asset', kind: 'input', label: 'Asset' },
        {
            id: 'sellPrice',
            kind: 'ref',
            label: 'Live price',
            ref: {
                connector: 'coinmarketcap',
                metric: 'spot_usd',
                symbolFrom: 'asset',
            },
        },
        {
            id: 'cost',
            kind: 'computed',
            label: 'Cost',
            expr: 'amount * buyPrice',
            deps: ['amount', 'buyPrice'],
        },
        {
            id: 'value',
            kind: 'computed',
            label: 'Value',
            expr: 'amount * sellPrice',
            deps: ['amount', 'sellPrice'],
        },
        {
            id: 'profit',
            kind: 'output',
            label: 'Profit',
            expr: 'value - cost',
            deps: ['value', 'cost'],
        },
        {
            id: 'roi',
            kind: 'output',
            label: 'ROI',
            expr: 'cost == 0 ? 0 : (profit / cost) * 100',
            deps: ['profit', 'cost'],
        },
    ],
    outputs: ['profit', 'roi'],
};

describe('client-side calculator graph', () => {
    it('orders nodes so every node follows its deps', () => {
        const order = topoOrder(profitGraph);
        expect(order.indexOf('cost')).toBeLessThan(order.indexOf('profit'));
        expect(order.indexOf('value')).toBeLessThan(order.indexOf('profit'));
        expect(order.indexOf('profit')).toBeLessThan(order.indexOf('roi'));
    });

    it('computes profit and ROI from inputs + a resolved ref (no server)', () => {
        // amount 2, buyPrice 100, sellPrice (ref) seeded at 150.
        const values = computeValues(profitGraph, {
            amount: 2,
            buyPrice: 100,
            sellPrice: 150,
        });

        expect(values.cost).toBe(200);
        expect(values.value).toBe(300);
        expect(values.profit).toBe(100);
        expect(values.roi).toBeCloseTo(50, 9);
    });

    it('guards ROI against a zero cost basis (no division by zero)', () => {
        const values = computeValues(profitGraph, {
            amount: 0,
            buyPrice: 0,
            sellPrice: 150,
        });
        expect(values.cost).toBe(0);
        expect(values.roi).toBe(0);
    });
});

// A collection calculator: allocate an investment across assets weighted by live market cap.
const allocationGraph: CalculatorGraph = {
    nodes: [
        { id: 'investAmount', kind: 'input', label: 'Invest' },
        {
            id: 'holdings',
            kind: 'collection',
            label: 'Assets',
            control: { type: 'collection', fields: [], default: [] },
        },
        {
            id: 'marketCap',
            kind: 'ref',
            label: 'Market cap',
            over: 'holdings',
            ref: {
                connector: 'coinmarketcap',
                metric: 'market_cap',
                symbolFrom: 'symbol',
            },
        },
        {
            id: 'weight',
            kind: 'each',
            label: 'Weight',
            over: 'holdings',
            expr: 'enabled ? marketCap : 0',
            deps: ['marketCap'],
        },
        {
            id: 'totalCap',
            kind: 'aggregate',
            label: 'Total',
            reducer: 'sum',
            of: 'weight',
            deps: ['weight'],
        },
        {
            id: 'allocation',
            kind: 'each',
            label: 'Allocation',
            over: 'holdings',
            expr: 'totalCap == 0 ? 0 : investAmount * (weight / totalCap)',
            deps: ['weight', 'totalCap'],
        },
        {
            id: 'sharePct',
            kind: 'each',
            label: 'Share',
            over: 'holdings',
            expr: 'totalCap == 0 ? 0 : (weight / totalCap) * 100',
            deps: ['weight', 'totalCap'],
        },
    ],
    outputs: ['totalCap'],
};

describe('collection calculator (map / reduce over rows)', () => {
    const collections = {
        holdings: [
            { symbol: 'BTC', enabled: true },
            { symbol: 'ETH', enabled: true },
            { symbol: 'SOL', enabled: false },
        ],
    };
    const baseVectors = { marketCap: [1200, 400, 100] };

    it('maps per-row weight, reduces the total, and allocates by market-cap share', () => {
        const { scalars, vectors } = evaluateGraph(
            allocationGraph,
            { investAmount: 1000 },
            collections,
            baseVectors,
        );

        expect(vectors.weight).toEqual([1200, 400, 0]); // disabled row zeroed
        expect(scalars.totalCap).toBe(1600);
        expect(vectors.allocation).toEqual([750, 250, 0]); // 1000 * cap/total
        expect(vectors.sharePct).toEqual([75, 25, 0]);
    });

    it('guards allocation when every row is excluded (total cap is zero)', () => {
        const allOff = {
            holdings: collections.holdings.map((row) => ({
                ...row,
                enabled: false,
            })),
        };
        const { scalars, vectors } = evaluateGraph(
            allocationGraph,
            { investAmount: 1000 },
            allOff,
            baseVectors,
        );

        expect(scalars.totalCap).toBe(0);
        expect(vectors.allocation).toEqual([0, 0, 0]);
    });
});

// A temporal fold: a balance that compounds each period and takes a fixed contribution.
const compoundingGraph: CalculatorGraph = {
    nodes: [
        { id: 'principal', kind: 'input', label: 'Principal' },
        { id: 'periodRate', kind: 'input', label: 'Rate' },
        { id: 'contribution', kind: 'input', label: 'Contribution' },
        { id: 'periods', kind: 'input', label: 'Periods' },
        {
            id: 'balance',
            kind: 'fold',
            label: 'Balance',
            count: 'periods',
            init: 'principal',
            step: 'acc * (1 + periodRate) + contribution',
            deps: ['principal', 'periodRate', 'contribution', 'periods'],
        },
        {
            id: 'totalContributed',
            kind: 'computed',
            label: 'Contributed',
            expr: 'principal + contribution * periods',
            deps: ['principal', 'contribution', 'periods'],
        },
        {
            id: 'totalGrowth',
            kind: 'output',
            label: 'Growth',
            expr: 'balance - totalContributed',
            deps: ['balance', 'totalContributed'],
        },
    ],
    outputs: ['balance', 'totalGrowth'],
};

describe('temporal fold (a recurring balance over periods)', () => {
    it('compounds the balance and exposes both the series and its final value', () => {
        // 1000 → *1.1 +100 → 1200 → 1420 → 1662 over three periods.
        const { scalars, vectors } = evaluateGraph(compoundingGraph, {
            principal: 1000,
            periodRate: 0.1,
            contribution: 100,
            periods: 3,
        });

        expect(vectors.balance).toHaveLength(3);
        [1200, 1420, 1662].forEach((expected, i) =>
            expect(vectors.balance[i] as number).toBeCloseTo(expected, 9),
        );
        expect(scalars.balance as number).toBeCloseTo(1662, 9); // final = last period
        expect(scalars.totalContributed).toBe(1300); // 1000 + 100 * 3
        expect(scalars.totalGrowth as number).toBeCloseTo(362, 9); // 1662 - 1300
    });

    it('returns the seed and an empty series when there are zero periods', () => {
        const { scalars, vectors } = evaluateGraph(compoundingGraph, {
            principal: 1000,
            periodRate: 0.1,
            contribution: 100,
            periods: 0,
        });

        expect(vectors.balance).toEqual([]);
        expect(scalars.balance).toBe(1000); // no steps → the init value
        expect(scalars.totalGrowth).toBe(0);
    });

    it('accepts a literal period count as well as a node id', () => {
        const literalCount: CalculatorGraph = {
            nodes: [
                { id: 'principal', kind: 'input', label: 'Principal' },
                { id: 'periodRate', kind: 'input', label: 'Rate' },
                {
                    id: 'balance',
                    kind: 'fold',
                    label: 'Balance',
                    count: 2,
                    init: 'principal',
                    step: 'acc * (1 + periodRate)',
                    deps: ['principal', 'periodRate'],
                },
            ],
            outputs: ['balance'],
        };
        const { scalars, vectors } = evaluateGraph(literalCount, {
            principal: 100,
            periodRate: 0.5,
        });

        expect(vectors.balance).toEqual([150, 225]); // 100 → 150 → 225
        expect(scalars.balance).toBe(225);
    });
});

// DCA: per-buy coins summed to totals, marked against a live spot price.
const dcaGraph: CalculatorGraph = {
    nodes: [
        {
            id: 'spot',
            kind: 'ref',
            label: 'Live price',
            ref: {
                connector: 'coinmarketcap',
                metric: 'spot_usd',
                symbolFrom: 'asset',
            },
        },
        {
            id: 'buys',
            kind: 'collection',
            label: 'Buys',
            control: { type: 'collection', fields: [], default: [] },
        },
        {
            id: 'coins',
            kind: 'each',
            label: 'Coins',
            over: 'buys',
            expr: 'price == 0 ? 0 : usd / price',
        },
        {
            id: 'spentRow',
            kind: 'each',
            label: 'Spent',
            over: 'buys',
            expr: 'usd',
        },
        {
            id: 'totalCoins',
            kind: 'aggregate',
            label: 'Total coins',
            reducer: 'sum',
            of: 'coins',
            deps: ['coins'],
        },
        {
            id: 'totalSpent',
            kind: 'aggregate',
            label: 'Total spent',
            reducer: 'sum',
            of: 'spentRow',
            deps: ['spentRow'],
        },
        {
            id: 'avgCost',
            kind: 'output',
            label: 'Average cost',
            expr: 'totalCoins == 0 ? 0 : totalSpent / totalCoins',
            deps: ['totalSpent', 'totalCoins'],
        },
        {
            id: 'pnl',
            kind: 'output',
            label: 'P/L',
            expr: 'totalCoins * spot - totalSpent',
            deps: ['totalCoins', 'spot', 'totalSpent'],
        },
        {
            id: 'roi',
            kind: 'output',
            label: 'ROI',
            expr: 'totalSpent == 0 ? 0 : (pnl / totalSpent) * 100',
            deps: ['pnl', 'totalSpent'],
        },
    ],
    outputs: ['avgCost', 'pnl', 'roi'],
};

describe('dollar-cost averaging (collection + live spot)', () => {
    it('averages the cost across buys and marks P/L against the spot price', () => {
        const collections = {
            buys: [
                { usd: 1000, price: 20000 },
                { usd: 1000, price: 40000 },
            ],
        };
        const { scalars, vectors } = evaluateGraph(
            dcaGraph,
            { spot: 30000 },
            collections,
        );

        expect(vectors.coins).toEqual([0.05, 0.025]);
        expect(scalars.totalSpent).toBe(2000);
        expect(scalars.totalCoins as number).toBeCloseTo(0.075, 9);
        expect(scalars.avgCost as number).toBeCloseTo(26666.6667, 3); // 2000 / 0.075
        expect(scalars.pnl as number).toBeCloseTo(250, 9); // 0.075 * 30000 - 2000
        expect(scalars.roi as number).toBeCloseTo(12.5, 9); // 250 / 2000 * 100
    });
});

// Converter: two live prices, a converted amount, and the effective rate — guarded against a
// zero destination price.
const converterGraph: CalculatorGraph = {
    nodes: [
        { id: 'amount', kind: 'input', label: 'Amount' },
        {
            id: 'fromPrice',
            kind: 'ref',
            label: 'From price',
            ref: {
                connector: 'coinmarketcap',
                metric: 'spot_usd',
                symbolFrom: 'fromCoin',
            },
        },
        {
            id: 'toPrice',
            kind: 'ref',
            label: 'To price',
            ref: {
                connector: 'coinmarketcap',
                metric: 'spot_usd',
                symbolFrom: 'toCoin',
            },
        },
        {
            id: 'converted',
            kind: 'output',
            label: 'Converted',
            expr: 'toPrice == 0 ? 0 : (amount * fromPrice) / toPrice',
            deps: ['amount', 'fromPrice', 'toPrice'],
        },
        {
            id: 'rate',
            kind: 'output',
            label: 'Rate',
            expr: 'toPrice == 0 ? 0 : fromPrice / toPrice',
            deps: ['fromPrice', 'toPrice'],
        },
    ],
    outputs: ['converted', 'rate'],
};

describe('crypto converter (division by the destination price)', () => {
    it('converts one coin into another at the live rate', () => {
        const values = computeValues(converterGraph, {
            amount: 2,
            fromPrice: 60000,
            toPrice: 3000,
        });
        expect(values.converted).toBe(40); // (2 * 60000) / 3000
        expect(values.rate).toBe(20); // 60000 / 3000
    });

    it('guards against a zero destination price', () => {
        const values = computeValues(converterGraph, {
            amount: 2,
            fromPrice: 60000,
            toPrice: 0,
        });
        expect(values.converted).toBe(0);
        expect(values.rate).toBe(0);
    });
});

// Capital gains: short- vs long-term rate chosen by holding days.
const capitalGainsGraph: CalculatorGraph = {
    nodes: [
        { id: 'proceeds', kind: 'input', label: 'Proceeds' },
        { id: 'costBasis', kind: 'input', label: 'Cost basis' },
        { id: 'holdingDays', kind: 'input', label: 'Days held' },
        { id: 'shortRate', kind: 'input', label: 'Short rate' },
        { id: 'longRate', kind: 'input', label: 'Long rate' },
        {
            id: 'gain',
            kind: 'computed',
            label: 'Gain',
            expr: 'proceeds - costBasis',
            deps: ['proceeds', 'costBasis'],
        },
        {
            id: 'isLong',
            kind: 'computed',
            label: 'Long?',
            expr: 'holdingDays >= 365 ? 1 : 0',
            deps: ['holdingDays'],
        },
        {
            id: 'rate',
            kind: 'computed',
            label: 'Rate',
            expr: 'isLong == 1 ? longRate : shortRate',
            deps: ['isLong', 'longRate', 'shortRate'],
        },
        {
            id: 'taxOwed',
            kind: 'output',
            label: 'Tax',
            expr: 'gain > 0 ? gain * rate / 100 : 0',
            deps: ['gain', 'rate'],
        },
        {
            id: 'netProceeds',
            kind: 'output',
            label: 'Net',
            expr: 'proceeds - taxOwed',
            deps: ['proceeds', 'taxOwed'],
        },
    ],
    outputs: ['gain', 'taxOwed', 'netProceeds'],
};

describe('capital gains (short- vs long-term ternary)', () => {
    const inputs = {
        proceeds: 12000,
        costBasis: 8000,
        shortRate: 24,
        longRate: 15,
    };

    it('applies the long-term rate past one year held', () => {
        const values = computeValues(capitalGainsGraph, {
            ...inputs,
            holdingDays: 400,
        });
        expect(values.gain).toBe(4000);
        expect(values.rate).toBe(15);
        expect(values.taxOwed).toBe(600); // 4000 * 15 / 100
        expect(values.netProceeds).toBe(11400);
    });

    it('applies the short-term rate under one year held', () => {
        const values = computeValues(capitalGainsGraph, {
            ...inputs,
            holdingDays: 100,
        });
        expect(values.rate).toBe(24);
        expect(values.taxOwed).toBe(960); // 4000 * 24 / 100
        expect(values.netProceeds).toBe(11040);
    });
});
