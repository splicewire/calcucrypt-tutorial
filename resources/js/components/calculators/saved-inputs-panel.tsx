import { usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { UseCalculator } from '@/calculators/useCalculator';
import {
    useApiSavedInputsDestroy,
    useApiSavedInputsIndex,
    useApiSavedInputsStore,
} from '@/generated/hooks/api-saved-inputs';

// A signed-in reader's saved inputs for this calculator: save the current inputs under a name, load one back,
// or delete it. Every call goes through the `saved-inputs` resource's generated client, with the session, so
// the server keeps each reader to their own.
export function SavedInputsPanel({
    slug,
    calc,
}: {
    slug: string;
    calc: UseCalculator;
}) {
    const { auth } = usePage<{ auth: { user: unknown } }>().props;
    const [name, setName] = useState('');
    const saved = useApiSavedInputsIndex({ enabled: Boolean(auth.user) });
    const store = useApiSavedInputsStore({
        onSuccess: () => {
            setName('');
            void saved.refetch();
        },
    });
    const destroy = useApiSavedInputsDestroy({
        onSuccess: () => void saved.refetch(),
    });

    if (!auth.user) {
        return null;
    }

    const mine = (saved.data ?? []).filter((row) => row.calculator === slug);

    // One update for the whole saved set, so every field lands and a ref input re-resolves once.
    const load = (inputs: Record<string, number | string>) =>
        calc.setInputs(inputs);

    return (
        <section className="rounded-lg border border-border bg-card p-5 sm:p-6">
            <p className="mb-4 font-mono text-[11px] tracking-widest text-muted-foreground uppercase">
                Your saved inputs
            </p>
            <form
                className="flex gap-2"
                onSubmit={(event) => {
                    event.preventDefault();
                    store.mutate({
                        body: { calculator: slug, name, inputs: calc.inputs },
                    });
                }}
            >
                <input
                    value={name}
                    onChange={(event) => setName(event.target.value)}
                    placeholder="Name these inputs"
                    aria-label="Name these inputs"
                    className="min-w-0 flex-1 rounded-md border border-border bg-background px-3 py-1.5 text-sm"
                />
                <button
                    type="submit"
                    disabled={name.trim() === '' || store.isPending}
                    className="rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground disabled:opacity-50"
                >
                    Save
                </button>
            </form>
            {mine.length > 0 && (
                <ul className="mt-4 divide-y divide-border text-sm">
                    {mine.map((row) => (
                        <li
                            key={row.id}
                            className="flex items-center justify-between gap-2 py-2"
                        >
                            <button
                                type="button"
                                className="text-left text-foreground hover:underline"
                                onClick={() => load(row.inputs)}
                            >
                                {row.name}
                            </button>
                            <button
                                type="button"
                                className="font-mono text-xs text-muted-foreground hover:text-foreground"
                                onClick={() =>
                                    destroy.mutate({ params: { id: row.id } })
                                }
                            >
                                delete
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
