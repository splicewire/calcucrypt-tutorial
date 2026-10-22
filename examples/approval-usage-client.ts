import assert from 'node:assert/strict';
import { defaults } from '../resources/js/generated/routes';
import type { ApprovalUsageData } from '../resources/js/generated/App/Data';

export function readApprovalUsage(value: unknown): ApprovalUsageData {
    assert.ok(value !== null && typeof value === 'object');
    const page = value as Record<string, unknown>;
    assert.ok(Array.isArray(page.data));
    assert.equal(page.data.length, 1);
    assert.equal(page.nextCursor, null);
    assert.ok(
        typeof page.limit === 'number' &&
            Number.isInteger(page.limit) &&
            page.limit > 0,
    );
    assert.equal('total' in page, false);
    const row = page.data[0] as Record<string, unknown>;
    assert.ok(row !== null && typeof row === 'object');
    assert.equal(row.id, 'approvals');
    assert.equal(row.meter, 'service-request.approval');
    assert.equal(row.unit, 'approvals');
    assert.equal(row.period, 'lifetime');
    assert.ok(
        typeof row.quantity === 'number' &&
            Number.isSafeInteger(row.quantity) &&
            row.quantity >= 0,
    );
    return {
        id: 'approvals',
        meter: 'service-request.approval',
        quantity: row.quantity,
        unit: 'approvals',
        period: 'lifetime',
    };
}

export async function runApprovalUsageExample(
    base: string,
    actors: { owner: { token: string }; outsider: { token: string } },
    expected: number,
) {
    assert.match(base, /^http:\/\/127\.0\.0\.1:\d+$/);
    assert.ok(Number.isSafeInteger(expected) && expected >= 0);
    const route = defaults['service-request-usage.index'];
    assert.equal(route, 'api/service-request-usage');
    const calls: { method: string; path: string; status: number }[] = [];
    async function read(token: string) {
        const path = '/' + route;
        const response = await fetch(base + path, {
            headers: {
                Accept: 'application/json',
                Authorization: 'Bearer ' + token,
            },
            redirect: 'error',
        });
        calls.push({ method: 'GET', path, status: response.status });
        assert.equal(response.status, 200);
        return readApprovalUsage(await response.json());
    }
    const a = await read(actors.owner.token);
    const b = await read(actors.outsider.token);
    assert.equal(a.quantity, expected);
    assert.equal(b.quantity, 0);
    return { calls, a, b };
}
