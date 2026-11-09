import { Head, router } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import { CardGrid, EmptyState, GuideCard } from '@/components/crypt/cards';
import { Input } from '@/components/ui/input';

interface Props {
    guides: App.Data.GuideSummaryData[];
    filters: { q?: string };
}

export default function GuidesIndex({ guides, filters }: Props) {
    const [q, setQ] = useState(filters.q ?? '');

    const onSubmit = (event: FormEvent) => {
        event.preventDefault();
        router.get('/guides', q.trim() ? { filter: { q: q.trim() } } : {}, { preserveState: true });
    };

    return (
        <>
            <Head title="Guides" />
            <div className="mx-auto max-w-6xl px-4 py-10 sm:px-6 lg:px-8">
                <header className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-3xl font-semibold tracking-tight text-foreground">Guides</h1>
                        <p className="mt-2 text-muted-foreground">Editorial walkthroughs with the real tools embedded.</p>
                    </div>
                    <form onSubmit={onSubmit} className="w-full sm:w-64">
                        <Input
                            type="search"
                            value={q}
                            onChange={(event) => setQ(event.target.value)}
                            placeholder="Filter guides"
                            aria-label="Filter guides"
                        />
                    </form>
                </header>

                {guides.length === 0 ? (
                    <EmptyState>No guides match your filter.</EmptyState>
                ) : (
                    <CardGrid>
                        {guides.map((guide) => (
                            <GuideCard key={guide.slug} guide={guide} />
                        ))}
                    </CardGrid>
                )}
            </div>
        </>
    );
}
