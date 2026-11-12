import { createElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { afterEach, expect, it, vi } from 'vitest';
import AccountTokens from '../pages/account/tokens';

const requests = vi.hoisted(() => [] as Promise<unknown>[]);
vi.mock('@inertiajs/react', () => ({ Head: () => null }));
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }));
vi.mock('@splicewire/beam-accounts', async (importOriginal) => {
    const accounts =
        await importOriginal<typeof import('@splicewire/beam-accounts')>();
    return {
        ...accounts,
        // Replace only roster chrome. The page, provider, hook, factory and request remain real.
        TokensRoster: () => {
            requests.push(accounts.useTokensServices().client.list());
            return null;
        },
    };
});

afterEach(() => {
    vi.unstubAllGlobals();
    requests.length = 0;
});

it('passes actual page endpoint props through the real provider and client to fetch', async () => {
    const fetch = vi
        .fn<typeof globalThis.fetch>()
        .mockResolvedValue(new Response(JSON.stringify({ data: [] })));
    vi.stubGlobal('fetch', fetch);
    renderToStaticMarkup(
        createElement(AccountTokens, {
            endpoints: {
                index: '/proof/accounts/tokens',
                permissions: '/proof/accounts/tokens/permissions',
                sessionsOthers: '/proof/accounts/tokens/sessions/others',
            },
        }),
    );
    await expect(Promise.all(requests)).resolves.toEqual([[]]);
    expect(fetch).toHaveBeenCalledTimes(1);
    expect(fetch.mock.calls[0][0]).toBe('/proof/accounts/tokens');
    expect(fetch.mock.calls[0][1]?.credentials).toBe('same-origin');
});
