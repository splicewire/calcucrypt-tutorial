import assert from 'node:assert/strict';
import { defaults } from '../resources/js/generated/routes';
import type { ServiceRequestData } from '../resources/js/generated/App/Data';

// This local Node driver consumes generated routes/response types. It does not execute React hooks.
const base = new URL(
    process.env.SERVICE_REQUEST_BASE_URL ?? 'http://127.0.0.1:46210',
);
assert.equal(base.protocol, 'http:');
assert.ok(
    ['127.0.0.1', 'localhost'].includes(base.hostname),
    'Use an isolated localhost fixture',
);
const ownerToken = process.env.SERVICE_REQUEST_TOKEN;
const otherToken = process.env.SERVICE_REQUEST_OTHER_TOKEN;
const ownerId = process.env.SERVICE_REQUEST_OWNER_ID;
assert.ok(
    ownerToken && otherToken && ownerId,
    'Supply a private fixture file through SERVICE_REQUEST_FIXTURE',
);
const requests: { method: string; path: string; status: number }[] = [];

async function request<T>(
    name: string,
    method: string,
    token: string | undefined,
    expected: number,
    body?: unknown,
    id?: number,
): Promise<T> {
    const template = defaults[name];
    assert.ok(template, `Missing generated route ${name}; regenerate assets`);
    const path = `/${template.replace('{id}', encodeURIComponent(String(id)))}`;
    const response = await fetch(new URL(path, base), {
        method,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            ...(token ? { Authorization: `Bearer ${token}` } : {}),
        },
        ...(body === undefined ? {} : { body: JSON.stringify(body) }),
        redirect: 'error',
    });
    requests.push({ method, path, status: response.status });
    assert.equal(response.status, expected, `${method} ${path}`);
    const json = (await response.json()) as { data: T };

    return json.data;
}

const title = `Restore email delivery ${Date.now()}`;
const created = await request<ServiceRequestData>(
    'service-requests.store',
    'POST',
    ownerToken,
    201,
    { title, status: 'open' },
);
assert.equal(typeof created.id, 'number');
assert.equal(created.title, title);
assert.equal(created.status, 'open');
const listed = await request<ServiceRequestData[]>(
    'service-requests.index',
    'GET',
    ownerToken,
    200,
);
assert.ok(listed.some((row) => row.id === created.id && row.title === title));
const shown = await request<ServiceRequestData>(
    'service-requests.show',
    'GET',
    ownerToken,
    200,
    undefined,
    created.id,
);
assert.deepEqual(shown, created);

await request('service-requests.store', 'POST', ownerToken, 422, {
    title: '',
    status: 'pending',
});
const afterInvalid = await request<ServiceRequestData[]>(
    'service-requests.index',
    'GET',
    ownerToken,
    200,
);
assert.equal(
    afterInvalid.length,
    listed.length,
    'Invalid input must persist no row',
);
await request('service-requests.index', 'GET', undefined, 401);
const shared = await request<ServiceRequestData[]>(
    'service-requests.index',
    'GET',
    otherToken,
    200,
);
assert.ok(shared.some((row) => row.id === created.id));
assert.deepEqual(
    await request<ServiceRequestData>(
        'service-requests.show',
        'GET',
        otherToken,
        200,
        undefined,
        created.id,
    ),
    created,
);
const outsiderToken = process.env.SERVICE_REQUEST_OUTSIDER_TOKEN;
const teamlessToken = process.env.SERVICE_REQUEST_TEAMLESS_TOKEN;
assert.ok(outsiderToken && teamlessToken);
const outsiders = await request<ServiceRequestData[]>(
    'service-requests.index',
    'GET',
    outsiderToken,
    200,
);
assert.ok(outsiders.every((row) => row.id !== created.id));
await request(
    'service-requests.show',
    'GET',
    outsiderToken,
    404,
    undefined,
    created.id,
);
await request('service-requests.index', 'GET', teamlessToken, 403);
await request('service-requests.store', 'POST', teamlessToken, 403, {
    title: 'Refused teamless request',
});
const attempted = await request<ServiceRequestData>(
    'service-requests.store',
    'POST',
    otherToken,
    201,
    {
        title: 'Ownership attempt',
        user_id: process.env.SERVICE_REQUEST_OUTSIDER_ID,
        userId: process.env.SERVICE_REQUEST_OUTSIDER_ID,
        team_id: Number(process.env.SERVICE_REQUEST_OUTSIDER_TEAM_ID),
        teamId: Number(process.env.SERVICE_REQUEST_OUTSIDER_TEAM_ID),
    },
);
const otherRows = await request<ServiceRequestData[]>(
    'service-requests.index',
    'GET',
    otherToken,
    200,
);
assert.ok(otherRows.some((row) => row.id === attempted.id));
await request(
    'service-requests.show',
    'GET',
    ownerToken,
    200,
    undefined,
    attempted.id,
);
const outsideCreated = await request<ServiceRequestData>(
    'service-requests.store',
    'POST',
    outsiderToken,
    201,
    { title: 'Team B only' },
);
await request(
    'service-requests.show',
    'GET',
    ownerToken,
    404,
    undefined,
    outsideCreated.id,
);
console.log(
    JSON.stringify(
        { created, listed, shown, attempted, requests, result: 'passed' },
        null,
        2,
    ),
);
