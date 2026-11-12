import { afterEach, describe, expect, it, vi } from 'vitest';
import { api } from './api';
import { teamClient } from './accounts-api';

afterEach(() => vi.unstubAllGlobals());

describe('host adapters over the packaged session request', () => {
    it('keeps the generated SDK envelope while carrying the current session headers and JSON body', async () => {
        vi.stubGlobal('document', { cookie: 'XSRF-TOKEN=encoded%2Btoken' });
        const fetch = vi
            .fn()
            .mockResolvedValue(
                new Response(JSON.stringify({ data: { id: 'saved' } }), {
                    status: 201,
                }),
            );
        vi.stubGlobal('fetch', fetch);
        const response = await api.post('/api/saved-inputs', {
            name: 'Named inputs',
        });
        expect(response.data.data).toEqual({ id: 'saved' });
        const [url, init] = fetch.mock.calls[0];
        expect(url).toBe('/api/saved-inputs');
        expect(init.credentials).toBe('same-origin');
        expect(init.method).toBe('POST');
        expect(new Headers(init.headers).get('X-XSRF-TOKEN')).toBe(
            'encoded+token',
        );
        expect(init.body).toBe(JSON.stringify({ name: 'Named inputs' }));
    });

    it('accepts an empty 204 through generated and team DELETE adapters', async () => {
        const fetch = vi
            .fn()
            .mockImplementation(() =>
                Promise.resolve(new Response(null, { status: 204 })),
            );
        vi.stubGlobal('fetch', fetch);
        expect(
            (await api.delete('/api/saved-inputs/id')).data.data,
        ).toBeUndefined();
        await expect(
            teamClient.removeMember('member'),
        ).resolves.toBeUndefined();
        expect(fetch.mock.calls.map(([url]) => url)).toEqual([
            '/api/saved-inputs/id',
            '/beam/accounts/members/member',
        ]);
    });

    it.each([403, 422])(
        'retains server refusal status, message and field errors for both adapters (%s)',
        async (status) => {
            const payload = {
                message: 'A precise refusal.',
                errors: { name: ['Choose another name.'] },
            };
            vi.stubGlobal(
                'fetch',
                vi
                    .fn()
                    .mockImplementation(() =>
                        Promise.resolve(
                            new Response(JSON.stringify(payload), { status }),
                        ),
                    ),
            );
            for (const send of [
                () => api.patch('/api/saved-inputs/id', { name: 'Taken' }),
                () =>
                    teamClient.updateRole({ userId: 'member', role: 'admin' }),
            ]) {
                await expect(send()).rejects.toMatchObject({
                    name: 'FrameActionError',
                    status,
                    message: payload.message,
                    errors: payload.errors,
                });
            }
        },
    );
});
