import { Head, router } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import { CalculatorCard, CardGrid, EmptyState } from '@/components/crypt/cards';
import { Input } from '@/components/ui/input';

interface Props {
    calculators: App.Data.CalculatorSummaryData[];
    filters: { q?: string; key?: string };
}

export default function CalculatorsIndex({ calculators, filters }: Props) {
    const [q, setQ] = useState(filters.q ?? '');

    const onSubmit = (event: FormEvent) => {
        event.preventDefault();
        router.get('/calculators', q.trim() ? { filter: { q: q.trim() } } : {}, { preserveState: true });
    };

    return (
        <>
            <Head title="Calculators" />
            <div className="mx-auto max-w-6xl px-4 py-10 sm:px-6 lg:px-8">
                <header className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-3xl font-semibold tracking-tight text-foreground">Calculators</h1>
                        <p className="mt-2 text-muted-foreground">
                            {calculators.length} grounded {calculators.length === 1 ? 'tool' : 'tools'}, every figure cited.
                        </p>
                    </div>
                    <form onSubmit={onSubmit} className="w-full sm:w-64">
                        <Input
                            type="search"
                            value={q}
                            onChange={(event) => setQ(event.target.value)}
                            placeholder="Filter calculators"
                            aria-label="Filter calculators"
                        />
                    </form>
                </header>

                {calculators.length === 0 ? (
                    <EmptyState>No calculators match your filter.</EmptyState>
                ) : (
                    <CardGrid>
                        {calculators.map((calculator) => (
                            <CalculatorCard key={calculator.slug} calculator={calculator} />
                        ))}
                    </CardGrid>
                )}
            </div>
        </>
    );
}
