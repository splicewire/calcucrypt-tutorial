import type { BuyerEntitlementInspectionData } from '@/generated/App/Data/Pages';
import { Head, router, usePage } from '@inertiajs/react';
import { Button } from '@schemastud/ui';
import {
    CreatorProvider,
    CreatorWorkspaceArea,
    ExtensionsProvider,
    ExtensionsArea,
    createCreatorClient,
    createExtensionsClient,
} from '@splicewire/beam-market';
import {
    QueryClient,
    QueryClientProvider,
    useMutation,
    useQuery,
    useQueryClient,
} from '@tanstack/react-query';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import type { LocalMarketplacePageData } from '@/generated/App/Data/Pages';
import type {
    PendingListingReviewData,
    ReviewDecisionData,
} from '@/generated/Splicewire/Beam/Market/Data';
import { defaults, operatorDefaults } from '@/generated/routes';

function url(name: string, id?: string | number): string {
    const template = defaults[name] ?? operatorDefaults[name];
    if (!template)
        throw new Error(`Missing generated marketplace route: ${name}`);
    return '/' + template.replace('{id}', encodeURIComponent(String(id ?? '')));
}

async function request<T>(
    method: string,
    path: string,
    params?: Record<string, string | number>,
    body?: unknown,
): Promise<{
    status: number;
    data: { data: T; total?: number; offset?: number; limit?: number };
}> {
    const target = new URL(path, window.location.origin);
    for (const [key, value] of Object.entries(params ?? {}))
        target.searchParams.set(key, String(value));
    const token = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)?.[1];
    const response = await fetch(target, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(token ? { 'X-XSRF-TOKEN': decodeURIComponent(token) } : {}),
        },
        ...(body === undefined ? {} : { body: JSON.stringify(body) }),
    });
    const data =
        response.status === 204 ? { data: undefined } : await response.json();
    if (!response.ok)
        throw Object.assign(
            new Error(
                response.status === 403
                    ? 'You do not have access to this marketplace action.'
                    : response.status === 402
                      ? 'Payment not completed. Nothing was purchased.'
                      : 'The marketplace request failed. Please retry.',
            ),
            { status: response.status },
        );
    return { status: response.status, data };
}

function ReviewQueue() {
    const queries = useQueryClient();
    const pending = useQuery({
        queryKey: ['local-marketplace-review'],
        queryFn: async () =>
            (
                await request<PendingListingReviewData[]>(
                    'GET',
                    url('local-marketplace.review.pending'),
                )
            ).data.data,
    });
    const decision = useMutation({
        mutationFn: async ({
            id,
            approve,
            reason,
        }: {
            id: string;
            approve: boolean;
            reason?: string;
        }) => {
            const result = (
                await request<ReviewDecisionData>(
                    'POST',
                    url(
                        'local-marketplace.review.market-products.' +
                            (approve ? 'approve' : 'reject'),
                        id,
                    ),
                    undefined,
                    approve ? undefined : { reason },
                )
            ).data.data;
            if (!result.applied)
                throw new Error(
                    'The review was not applied. Reload the queue before retrying.',
                );
            return result;
        },
        onSuccess: () => {
            toast.success('Review applied.');
            void queries.invalidateQueries();
        },
        onError: () =>
            toast.error('The review could not be applied. Reload and retry.'),
    });
    const [reason, setReason] = useState('');
    return (
        <section aria-label="Listing review" className="space-y-4">
            <h2 className="text-lg font-semibold">Listing review</h2>
            {pending.isPending ? (
                <p role="status">Loading review queue…</p>
            ) : pending.isError ? (
                <p role="alert">
                    Unable to load reviews.{' '}
                    <Button onClick={() => void pending.refetch()}>
                        Retry
                    </Button>
                </p>
            ) : (
                <>
                    {pending.data.length === 0 ? (
                        <p>No listings await review.</p>
                    ) : (
                        <>
                            <label className="block">
                                Rejection reason
                                <input
                                    value={reason}
                                    onChange={(event) =>
                                        setReason(event.target.value)
                                    }
                                    className="bg-background mt-1 block w-full rounded border p-2"
                                />
                            </label>
                            {pending.data.map((row) => (
                                <article
                                    key={row.marketId}
                                    className="space-y-2 rounded border p-4"
                                >
                                    <h3 className="font-semibold">
                                        {row.title}
                                    </h3>
                                    <p>
                                        {row.sellerName} ·{' '}
                                        {row.isFree ? 'Free' : row.priceLabel}
                                    </p>
                                    <div className="flex flex-wrap gap-2">
                                        <Button
                                            disabled={decision.isPending}
                                            onClick={() => {
                                                if (
                                                    window.confirm(
                                                        `Approve ${row.title}?`,
                                                    )
                                                )
                                                    decision.mutate({
                                                        id: row.marketId,
                                                        approve: true,
                                                    });
                                            }}
                                        >
                                            Approve {row.title}
                                        </Button>
                                        <Button
                                            variant="outline"
                                            disabled={
                                                decision.isPending ||
                                                !reason.trim()
                                            }
                                            onClick={() => {
                                                if (
                                                    window.confirm(
                                                        `Reject ${row.title}?`,
                                                    )
                                                )
                                                    decision.mutate({
                                                        id: row.marketId,
                                                        approve: false,
                                                        reason,
                                                    });
                                            }}
                                        >
                                            Reject {row.title}
                                        </Button>
                                    </div>
                                </article>
                            ))}
                        </>
                    )}
                </>
            )}
        </section>
    );
}

function BuyerLicenses({ rows }: { rows: BuyerEntitlementInspectionData[] }) {
    const revoke = useMutation({
        mutationFn: async (row: BuyerEntitlementInspectionData) =>
            request(
                'POST',
                url('market-extensions.revoke-entitlement', row.productId),
                undefined,
                { entitlementId: row.entitlementId },
            ),
        onSuccess: () => router.reload({ only: ['buyerEntitlements'] }),
    });
    return (
        <section aria-label="Buyer licenses" className="space-y-4">
            <h2 className="text-lg font-semibold">Buyer licenses</h2>
            <p className="text-muted-foreground text-sm">
                Revocation blocks future acquisition. Installed code and data
                remain. No refund is issued.
            </p>
            {revoke.isError && (
                <p role="alert">
                    Unable to revoke this buyer license. Please retry.
                </p>
            )}
            {rows.length === 0 ? (
                <p>No buyer purchases recorded.</p>
            ) : (
                rows.map((row) => (
                    <article
                        key={row.entitlementId}
                        className="space-y-2 break-words rounded border p-4"
                    >
                        <h3 className="font-semibold">{row.listing}</h3>
                        <p>
                            Buyer {row.buyerRef} ·{' '}
                            {row.scopeRef ?? 'No team scope'}
                        </p>
                        <p>
                            Status: {row.status} · Payment: {row.paymentRef}
                        </p>
                        <Button
                            disabled={
                                revoke.isPending || row.status === 'revoked'
                            }
                            onClick={() => {
                                if (
                                    window.confirm(
                                        `Revoke buyer ${row.buyerRef} license for ${row.listing} (${row.scopeRef ?? 'No team scope'})? Blocks future acquisition. Installed code and data remain.`,
                                    )
                                )
                                    revoke.mutate(row);
                            }}
                        >
                            Revoke buyer license
                        </Button>
                    </article>
                ))
            )}
        </section>
    );
}

function Marketplace({ props }: { props: LocalMarketplacePageData }) {
    const [tab, setTab] = useState('browse');
    const creator = useMemo(
        () =>
            createCreatorClient(
                (method, path, options) =>
                    request(method, path, options?.params, options?.body),
                {
                    seller: url('market-sellers.index'),
                    authorizations: url('seller-repo-authorizations.index'),
                    inspect: (id) =>
                        url('seller-repo-authorizations.inspect', id),
                    ...(props.simulatedCallback
                        ? {
                              simulateProviderCallback: (id: string) =>
                                  url(
                                      'seller-repo-authorizations.simulate-provider-callback',
                                      id,
                                  ),
                          }
                        : {}),
                    listings: url('market-listings.index'),
                    listing: (id) => url('market-listings.show', id),
                    submit: (id) => url('market-listings.submit', id),
                    release: (id) => url('market-listings.release', id),
                    withdraw: (id) => url('market-listings.withdraw', id),
                },
            ),
        [props.simulatedCallback],
    );
    const extensions = useMemo(
        () =>
            createExtensionsClient(request, {
                catalog: url('market-extensions.index'),
                listing: (id) => url('market-extensions.show', id),
                connection: url('local-marketplace.connection'),
                installed: url('installed-extensions.index'),
                install: (id) => url('market-extensions.install', id),
                purchase: (id) => url('market-extensions.purchase', id),
                update: (id) => url('installed-extensions.refresh', id),
                remove: (id) => url('installed-extensions.remove', id),
            }),
        [],
    );
    const notify = (event: { type: string; message: string }) =>
        event.type === 'success'
            ? toast.success(event.message)
            : toast.error(event.message);
    return (
        <main className="mx-auto max-w-6xl space-y-6 p-4 sm:p-6">
            <nav aria-label="Marketplace" className="flex flex-wrap gap-2">
                <Button
                    variant={tab === 'browse' ? 'default' : 'outline'}
                    onClick={() => setTab('browse')}
                >
                    Extensions
                </Button>
                {props.creator && (
                    <Button
                        variant={tab === 'creator' ? 'default' : 'outline'}
                        onClick={() => setTab('creator')}
                    >
                        Your extensions
                    </Button>
                )}
                {props.reviewer && (
                    <Button
                        variant={tab === 'review' ? 'default' : 'outline'}
                        onClick={() => setTab('review')}
                    >
                        Listing review
                    </Button>
                )}
                {props.reviewer && (
                    <Button
                        variant={tab === 'licenses' ? 'default' : 'outline'}
                        onClick={() => setTab('licenses')}
                    >
                        Buyer licenses
                    </Button>
                )}
                <a href="/service-desk" className="rounded px-3 py-2 underline">
                    Service desk
                </a>
            </nav>
            {props.paymentSandbox && (
                <p role="status" className="bg-muted rounded border p-3">
                    Local payment and payout sandbox — no charge.
                </p>
            )}
            {props.simulatedCallback && (
                <p className="text-muted-foreground text-sm">
                    Local repository preview: provider association is simulated.
                    Deployment requires an explicit terminal command.
                </p>
            )}
            {tab === 'creator' && props.creator ? (
                <CreatorProvider
                    services={{
                        client: creator,
                        notify,
                        onError: () =>
                            toast.error(
                                'The creator request failed. Please retry.',
                            ),
                    }}
                >
                    <CreatorWorkspaceArea />
                </CreatorProvider>
            ) : tab === 'licenses' && props.reviewer ? (
                <BuyerLicenses rows={props.buyerEntitlements} />
            ) : tab === 'review' && props.reviewer ? (
                <ReviewQueue />
            ) : (
                <ExtensionsProvider
                    services={{
                        client: extensions,
                        notify,
                        onError: () =>
                            toast.error(
                                'The extension request failed. Please retry.',
                            ),
                        can: (ability, listing) =>
                            ability === 'installed-extension.view' ||
                            (props.buyer &&
                                (ability === 'market-extensions.purchase' ||
                                    (ability === 'market-extensions.install' &&
                                        listing !== undefined &&
                                        !listing.isFree &&
                                        listing.isEntitled))) ||
                            (props.hostOperator &&
                                [
                                    'market-extensions.install',
                                    'installed-extensions.refresh',
                                    'installed-extensions.remove',
                                ].includes(ability)),
                    }}
                >
                    <ExtensionsArea />
                </ExtensionsProvider>
            )}
        </main>
    );
}

export default function LocalMarketplace() {
    const props = usePage<LocalMarketplacePageData>().props;
    const client = useMemo(
        () =>
            new QueryClient({ defaultOptions: { queries: { retry: false } } }),
        [props.cacheScope],
    );
    return (
        <>
            <Head title="Local marketplace" />
            <QueryClientProvider client={client}>
                <Marketplace key={props.cacheScope} props={props} />
            </QueryClientProvider>
        </>
    );
}
