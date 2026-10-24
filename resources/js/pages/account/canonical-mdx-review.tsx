import { useState } from 'react';
import { router } from '@inertiajs/react';
import { bodyClient } from '@splicewire/beam-inertia';
import { ReleasePanel } from '@splicewire/beam-ux';
import type { ReleaseRecord, ReleaseCommand } from '@splicewire/beam-ux';
import type { CanonicalMdxReviewPageData } from '@/generated/App/Data/Pages';

/** A version chooser and the shipped ReleasePanel, never an MDX canvas editor. */
export default function CanonicalMdxReviewPage({ entryId, title, versions, releases }: CanonicalMdxReviewPageData) {
    const [release, setRelease] = useState<ReleaseRecord | null>(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const create = async () => {
        setBusy(true); setError('');
        try {
            if (!bodyClient.createRelease) throw new Error('Release creation is unavailable.');
            setRelease(await bodyClient.createRelease(entryId, `Review: ${title}`));
        } catch (failure) { setError(failure instanceof Error ? failure.message : 'The Release could not be created.'); }
        finally { setBusy(false); }
    };
    const command = async (input: ReleaseCommand) => {
        if (!release || !bodyClient.releaseCommand) throw new Error('Release commands are unavailable.');
        setRelease(await bodyClient.releaseCommand(release.id, input));
    };
    const refresh = async () => {
        if (!release || !bodyClient.listReleases) return;
        setBusy(true); setError('');
        try {
            const current = (await bodyClient.listReleases()).find(item => item.id === release.id);
            if (!current) throw new Error('This Release is unavailable.');
            setRelease(current);
        } catch (failure) { setRelease(null); setError(failure instanceof Error ? failure.message : 'The Release is unavailable.'); }
        finally { setBusy(false); }
    };
    return <main className="mx-auto max-w-5xl space-y-4 p-6">
        <h1 className="text-2xl font-semibold">Review an MDX publication</h1>
        <p>{title}</p>
        <p>Choose an explicitly saved version. A later working draft does not replace a fixed Release member. Another author must review before publication.</p>
        <p>Publication writes the selected version’s mirror and records it locally. It does not check a last-minute external file hash.</p>
        {error && <p role="alert">{error}</p>}
        <label className="block min-w-0">Existing Release <select aria-label="Existing Release" className="block w-full min-w-0 max-w-full rounded border bg-background p-2" value={release?.id ?? ''} onChange={event => setRelease(releases.find(item => item.id === event.target.value) ?? null)}>
            <option value="">Choose a Release, or create one</option>
            {releases.map(item => <option key={item.id} value={item.id}>{item.title} ({item.status})</option>)}
        </select></label>
        <div className="flex flex-wrap gap-3">
            <button type="button" disabled={busy || release !== null} onClick={() => void create()} className="rounded-md border bg-background px-3 py-2">Create a Release</button>
            <button type="button" disabled={busy} onClick={() => router.reload({ only: ['versions'] })} className="rounded-md border bg-background px-3 py-2">Reload saved versions</button>
            {release && <button type="button" disabled={busy} onClick={() => void refresh()} className="rounded-md border bg-background px-3 py-2">Refresh Release</button>}
        </div>
        {release && <ReleasePanel release={release} entries={[{ id: entryId, title, versions }]} onCommand={command} />}
    </main>;
}
