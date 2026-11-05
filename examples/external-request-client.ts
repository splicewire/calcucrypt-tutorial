import assert from 'node:assert/strict';
import { defaults } from '../resources/js/generated/routes';
import type { ExternalRequestData } from '../resources/js/generated/App/Data';

// Generated route/row contract; cursor envelope is checked explicitly, not supplied by generated hooks.
export function readExternalPage(value: unknown): {
    rows: ExternalRequestData[];
    next: string | null;
    limit: number;
} {
    assert.ok(value !== null && typeof value === 'object');
    const page = value as Record<string, unknown>;
    assert.ok(Array.isArray(page.data));
    assert.ok(
        typeof page.limit === 'number' &&
            Number.isInteger(page.limit) &&
            page.limit > 0,
    );
    assert.ok(page.nextCursor === null || typeof page.nextCursor === 'string');
    assert.equal('total' in page, false);
    const rows: ExternalRequestData[] = page.data.map((row: unknown) => {
        assert.ok(row !== null && typeof row === 'object');
        const r = row as Record<string, unknown>;
        for (const key of [
            'id',
            'source',
            'externalId',
            'title',
            'status',
            'updatedAt',
        ])
            assert.equal(typeof r[key], 'string');
        assert.ok(r.status === 'open' || r.status === 'closed');
        assert.equal(r.id, r.source + ':' + r.externalId);
        return {
            id: r.id as string,
            source: r.source as string,
            externalId: r.externalId as string,
            title: r.title as string,
            status: r.status as string,
            updatedAt: r.updatedAt as string,
        };
    });
    return { rows, next: page.nextCursor as string | null, limit: page.limit };
}

export async function runExternalRequestExample(
    base: string,
    actors: { owner: { token: string }; outsider: { token: string } },
) {
    assert.match(base, /^http:\/\/127\.0\.0\.1:\d+$/);
    const route = defaults['external-requests.index'];
    assert.equal(route, 'api/external-requests');
    const calls: { method: string; path: string; status: number }[] = [];
    async function page(token: string, params: URLSearchParams) {
        const path = '/' + route + '?' + params;
        const response = await fetch(base + path, {
            headers: {
                Accept: 'application/json',
                Authorization: 'Bearer ' + token,
            },
            redirect: 'error',
        });
        calls.push({ method: 'GET', path, status: response.status });
        assert.equal(response.status, 200);
        return readExternalPage(await response.json());
    }
    const ids: string[] = [];
    let cursor: string | null = null;
    do {
        const params = new URLSearchParams({ perPage: '2' });
        if (cursor) params.set('cursor', cursor);
        const result = await page(actors.owner.token, params);
        assert.ok(result.rows.every((row) => row.source === 'fixture-desk-a'));
        ids.push(...result.rows.map((row) => row.id));
        assert.notEqual(
            result.next,
            cursor === null ? '' : cursor,
            'Cursor must advance',
        );
        cursor = result.next;
        assert.ok(calls.length < 10, 'Bound the teaching fixture traversal');
    } while (cursor);
    assert.equal(ids.length, 5);
    assert.equal(new Set(ids).size, ids.length);
    const closed = await page(
        actors.owner.token,
        new URLSearchParams({ 'filter[status]': 'closed' }),
    );
    assert.equal(closed.rows.length, 2);
    assert.ok(closed.rows.every((row) => row.status === 'closed'));
    const b = await page(
        actors.outsider.token,
        new URLSearchParams({ perPage: '2' }),
    );
    assert.deepEqual(
        b.rows.map((row) => row.id),
        ['fixture-desk-b:R2', 'fixture-desk-b:R1'],
    );
    assert.ok(!b.rows.some((row) => ids.includes(row.id)));
    return {
        calls,
        aIds: ids,
        bIds: b.rows.map((row) => row.id),
        filtered: closed.rows.map((row) => row.id),
    };
}
