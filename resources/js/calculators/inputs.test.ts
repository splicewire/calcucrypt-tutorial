import { describe, expect, it } from 'vitest';
import { applyInputs } from './inputs';

// The Profit calculator's scalar inputs; `asset` steers its live price ref.
const refInputs = new Set(['asset']);
const current = { asset: 'BTC', amount: 1, buyPrice: 100 };

describe('applyInputs', () => {
    it('applies every field of a saved set at once', () => {
        // Loading a saved set used to apply one field at a time from the same snapshot, so only the
        // last field survived: { asset: 'BTC', amount: 1, buyPrice: 200 }.
        const { next } = applyInputs(
            current,
            { asset: 'ETH', amount: 2, buyPrice: 200 },
            refInputs,
        );

        expect(next).toEqual({ asset: 'ETH', amount: 2, buyPrice: 200 });
    });

    it('applies the fields in any key order', () => {
        const { next } = applyInputs(
            current,
            { buyPrice: 200, asset: 'ETH', amount: 2 },
            refInputs,
        );

        expect(next).toEqual({ asset: 'ETH', amount: 2, buyPrice: 200 });
    });

    it('reprices when the set touches a ref input, so one resolve carries the complete set', () => {
        expect(
            applyInputs(current, { asset: 'ETH', amount: 2 }, refInputs)
                .reprices,
        ).toBe(true);
        expect(
            applyInputs(current, { amount: 2, buyPrice: 200 }, refInputs)
                .reprices,
        ).toBe(false);
    });

    it('keeps the single-field rule: setting a ref input reprices, even to the same value', () => {
        expect(applyInputs(current, { asset: 'BTC' }, refInputs).reprices).toBe(
            true,
        );
        expect(applyInputs(current, { amount: 3 }, refInputs)).toEqual({
            next: { ...current, amount: 3 },
            reprices: false,
        });
    });

    it('ignores ids the calculator does not have', () => {
        const { next, reprices } = applyInputs(
            current,
            { amount: 2, retired: 7 },
            refInputs,
        );

        expect(next).toEqual({ asset: 'BTC', amount: 2, buyPrice: 100 });
        expect(reprices).toBe(false);
    });

    it('leaves the current inputs untouched', () => {
        applyInputs(current, { asset: 'ETH' }, refInputs);

        expect(current).toEqual({ asset: 'BTC', amount: 1, buyPrice: 100 });
    });
});
