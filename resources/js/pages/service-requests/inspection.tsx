import { Head } from '@inertiajs/react';
import { useQuery } from '@tanstack/react-query';
import { route } from '@/lib/routes';
import type { ReadRequestApprovalHistoryOutputData } from '@/generated/App/Data';
import type { RequestApprovalInspectionPageData } from '@/generated/App/Data/Pages';

class HistoryReadError extends Error {
    constructor(readonly status: number) {
        super('History could not be loaded');
    }
}

async function readHistory(requestId: number, signal: AbortSignal): Promise<ReadRequestApprovalHistoryOutputData> {
    const response = await fetch(route('service-requests.approval-history', { id: requestId }), {
        signal,
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
    });
    if (!response.ok) throw new HistoryReadError(response.status);
    const envelope: { data: ReadRequestApprovalHistoryOutputData } = await response.json();
    return envelope.data;
}

export default function RequestApprovalInspection({ requestId, cacheScope, backHref }: RequestApprovalInspectionPageData) {
    const history = useQuery({
        queryKey: ['request-approval-history', cacheScope, requestId],
        queryFn: ({ signal }) => readHistory(requestId, signal),
        staleTime: 0,
        refetchOnMount: 'always',
        retry: false,
    });
    // A cached successful title/fact is not authority during another read or after denial.
    const data = history.isSuccess && !history.isFetching ? history.data : undefined;
    const status = history.error instanceof HistoryReadError ? history.error.status : undefined;
    const denied = status === 401 || status === 403;
    const missing = status === 404;
    const state = history.isFetching || history.isPending ? 'loading' : denied || missing ? 'forbidden' : history.isError ? 'error' : data?.facts.length ? 'ready' : 'empty';

    return <main className="mx-auto w-full max-w-4xl space-y-6 p-4 sm:p-6" data-page-state={state}>
        <Head title="Approval inspection" />
        <a href={backHref} className="inline-block underline underline-offset-4 focus-visible:outline-2">Back to request</a>
        <header className="space-y-2">
            <h1 className="break-words text-2xl font-semibold">Approval inspection</h1>
            <p className="text-sm text-muted-foreground">Only committed transitions; refused attempts and effect delivery are not recorded here.</p>
        </header>
        {state === 'loading' && <p role="status">Loading approval history…</p>}
        {(denied || missing) && !history.isFetching && <p role="alert">{missing ? 'Request unavailable in this team.' : 'You do not have access to this request.'}</p>}
        {state === 'error' && <div role="alert" className="space-y-3"><p>History could not be loaded</p><button type="button" className="rounded border px-3 py-2 focus-visible:outline-2" onClick={() => void history.refetch()}>Retry</button></div>}
        {data && <>
            <section aria-labelledby="current-heading" className="space-y-3 rounded-lg border p-4">
                <h2 id="current-heading" className="break-words text-xl font-medium">{data.title}</h2>
                <dl className="grid gap-3 sm:grid-cols-2">
                    <div><dt className="text-sm text-muted-foreground">Current approval</dt><dd>{data.currentApproval}</dd></div>
                    <div className="min-w-0"><dt className="text-sm text-muted-foreground">Current definition pin</dt><dd className="break-all font-mono text-sm">{data.currentPin ?? 'Not pinned'}</dd></div>
                </dl>
            </section>
            <section aria-labelledby="history-heading" className="space-y-4">
                <h2 id="history-heading" className="text-lg font-semibold">Committed transitions</h2>
                {data.facts.length === 0 ? <p>No committed approval transitions.</p> : <ol className="space-y-4">
                    {data.facts.map(fact => <li key={fact.id} className="space-y-3 rounded-lg border p-4">
                        <h3 className="break-words font-semibold">{fact.transition}: {fact.from.join(', ')} → {fact.to.join(', ')}</h3>
                        <dl className="grid gap-3 text-sm sm:grid-cols-2">
                            <div className="min-w-0"><dt className="text-muted-foreground">Fact</dt><dd className="break-all font-mono">{fact.id}</dd></div>
                            <div><dt className="text-muted-foreground">Committed at (UTC)</dt><dd className="break-all"><time dateTime={fact.occurredAt}>{fact.occurredAt}</time></dd></div>
                            <div className="min-w-0"><dt className="text-muted-foreground">Recorded actor</dt><dd className="break-all font-mono">{fact.actorReference ?? 'Unknown actor'}</dd></div>
                            <div className="min-w-0"><dt className="text-muted-foreground">Fact-time definition version</dt><dd className="break-all font-mono">{fact.versionId ?? 'Version not recorded'}</dd></div>
                        </dl>
                        {fact.definitionStatus === 'available' ? <p className="break-words">{fact.definitionName} · {fact.definitionKey} · version {fact.definitionVersion}</p> : <p className="text-sm text-muted-foreground">{fact.definitionStatus === 'not-recorded' ? 'Version not recorded' : 'Historical definition unavailable'}</p>}
                    </li>)}
                </ol>}
            </section>
        </>}
    </main>;
}
