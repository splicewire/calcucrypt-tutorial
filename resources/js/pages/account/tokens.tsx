import { Head } from '@inertiajs/react';
import { createSessionRequest } from '@schemastud/frame/session';
import {
    createTokensClient,
    TokensProvider,
    TokensRoster,
} from '@splicewire/beam-accounts';
import type {
    TokensEndpoints,
    TokensServices,
} from '@splicewire/beam-accounts';
import { useMemo } from 'react';
import { toast } from 'sonner';

/**
 * The ACCOUNT-realm API-tokens surface.
 *
 * The whole page body is `@splicewire/beam-accounts`' own <TokensRoster> — the mint dialog, the
 * reveal-once secret, the provenance facets, the archive/rotate/renew row actions and the
 * "revoke other sessions" sweep all travel inside the package, and so does the transport: the
 * package's `createTokensClient` over frame's session request, sent to the `endpoints` laravel-beam-accounts
 * builds from its own routes (`TokensEndpoints::props()`, passed by `routes/web.php`). This file writes no URL, so
 * a moved `ACCOUNT_API_ROOT` moves the page with it. It supplies only the feedback sink.
 *
 * The <AccountShell> chrome and the nav rail come from app.tsx's layout resolution for `account/*`
 * pages, so this file is pure content — the same "wrote only config" proof `account/home` makes,
 * one surface up.
 *
 * ⚠️ This REPLACES the tenant frame console's `/tokens` leaf, which has been removed from
 * `config('frame.realms')['tenant']`. `TokenData` is `readOnly: true` by declaration — the
 * reveal-once mint is a host escape hatch its own docblock names — so Frame's generic list rendered
 * no create control at all, correctly. Read config/frame.php's realm comment before adding it back.
 */
export default function AccountTokens({
    endpoints,
}: {
    endpoints: TokensEndpoints;
}) {
    const services = useMemo<TokensServices>(
        () => ({
            client: createTokensClient(createSessionRequest(), endpoints),
            // Success copy is the component's call; errors carry the server's own message, which for
            // this surface is usually the precise refusal ("You cannot archive the token you are
            // using.") rather than a status code.
            notify: (event) =>
                event.type === 'success'
                    ? toast.success(event.message)
                    : toast.error(event.message),
            onError: (error) =>
                toast.error(
                    error instanceof Error
                        ? error.message
                        : 'Something went wrong.',
                ),
        }),
        [endpoints],
    );

    return (
        <>
            <Head title="API tokens" />
            <div className="p-6">
                <TokensProvider services={services}>
                    <TokensRoster />
                </TokensProvider>
            </div>
        </>
    );
}
