import { Head, Link } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { EntryBody } from '@splicewire/beam-ux/site';
import { CalculatorWidget } from '@/components/calculators/calculator-widget';

type Guide = App.Data.GuideData;
type Embed = App.Data.CalculatorEmbedData;
type CalculatorSummary = App.Data.CalculatorSummaryData;

const bodyClass =
    'max-w-none text-foreground [&_h2]:mt-8 [&_h2]:mb-3 [&_h2]:text-xl [&_h2]:font-semibold [&_p]:my-4 [&_p]:leading-7 [&_p]:text-muted-foreground [&_ul]:my-4 [&_ul]:list-disc [&_ul]:pl-6 [&_li]:my-1 [&_a]:text-terminal-dim [&_a]:underline';

function formatDate(iso: string | null): string | null {
    if (!iso) return null;
    const date = new Date(iso);
    return Number.isNaN(date.getTime())
        ? null
        : date.toLocaleDateString('en-US', {
              month: 'short',
              day: 'numeric',
              year: 'numeric',
          });
}

/** UX compiles authored HTML markers as literal divs; portals keep their authored positions. */
function GuideBody({ guide, embeds }: { guide: Guide; embeds: Embed[] }) {
    const body = useRef<HTMLDivElement>(null);
    const [anchors, setAnchors] = useState<HTMLDivElement[]>([]);

    useEffect(() => {
        const root = body.current;
        if (!root) return;
        const refresh = () => {
            const next = [
                ...root.querySelectorAll<HTMLDivElement>('div[data-embed]'),
            ];
            setAnchors((current) =>
                current.length === next.length &&
                current.every((node, i) => node === next[i])
                    ? current
                    : next,
            );
        };
        const observer = new MutationObserver(refresh);
        observer.observe(root, { childList: true, subtree: true });
        refresh();
        return () => observer.disconnect();
    }, [guide.artifactVersion]);

    return (
        <>
            <div ref={body} className={bodyClass}>
                <EntryBody
                    artifact={{
                        url: guide.artifactUrl,
                        version: guide.artifactVersion,
                    }}
                />
            </div>
            {anchors.map((node) => {
                const anchor = node.dataset.embed;
                const embed = embeds.find((item) => item.anchor === anchor);
                return embed
                    ? createPortal(
                          <EmbeddedCalculator embed={embed} />,
                          node,
                          anchor,
                      )
                    : null;
            })}
        </>
    );
}

function EmbeddedCalculator({ embed }: { embed: Embed }) {
    return (
        <section className="my-8 rounded-xl border border-border bg-muted/30 p-4 sm:p-5">
            <div className="mb-4 flex items-center justify-between">
                <p className="font-mono text-[11px] tracking-widest text-terminal-dim uppercase">
                    {embed.spec.name}
                </p>
                <Link
                    href={`/calculators/${embed.spec.slug}`}
                    className="font-mono text-xs text-muted-foreground hover:text-foreground"
                >
                    Open full tool ↗
                </Link>
            </div>
            <p className="mb-3 text-sm text-muted-foreground">This preview is read-only. Open the full tool to change inputs.</p>
            <CalculatorWidget spec={embed.spec} resolution={embed.resolution} readOnly />
        </section>
    );
}

// The brand promise, rendered as the dark green callout from the prototype.
function CitedCallout({ calculator }: { calculator?: CalculatorSummary }) {
    return (
        <aside className="my-10 flex flex-col gap-4 rounded-xl border border-readout-border bg-readout p-6 text-readout-foreground sm:flex-row sm:items-center sm:justify-between">
            <p className="max-w-md text-sm">
                <span className="font-semibold text-terminal">
                    Never stale, always cited.
                </span>{' '}
                If a number can't prove where it came from, we don't show it as
                live.
            </p>
            <Link
                href={
                    calculator
                        ? `/calculators/${calculator.slug}`
                        : '/calculators'
                }
                className="inline-flex w-fit items-center rounded-md bg-terminal px-4 py-2 text-sm font-medium text-terminal-foreground transition-opacity hover:opacity-90"
            >
                Open the full tool
            </Link>
        </aside>
    );
}

function LinkCta({ calculator }: { calculator: CalculatorSummary }) {
    return (
        <Link
            href={`/calculators/${calculator.slug}`}
            className="block rounded-lg border border-border bg-card p-4 transition-colors hover:border-terminal"
        >
            <div className="flex items-start justify-between gap-2">
                <p className="font-mono text-[10px] tracking-widest text-terminal-dim uppercase">
                    Tool
                </p>

            </div>
            <h3 className="mt-1 text-base font-semibold text-foreground">
                Open the full tool: {calculator.name}
            </h3>
            <p className="mt-1.5 text-sm text-muted-foreground">
                {calculator.summary}
            </p>
        </Link>
    );
}

export default function GuideShow({
    guide,
    embeds,
    links,
}: {
    guide: Guide;
    embeds: Embed[];
    links: CalculatorSummary[];
}) {
    const published = formatDate(guide.publishedAt);
    // A spec carries every field of a summary, so an embed can stand in for a link card here.
    const calloutTool: CalculatorSummary | undefined =
        embeds[0]?.spec ?? links[0];

    return (
        <>
            <Head title={guide.title} />
            <article className="mx-auto max-w-3xl px-4 py-10 sm:px-6 lg:px-8">
                <nav className="mb-6 font-mono text-xs text-muted-foreground">
                    <Link href="/" className="hover:text-foreground">
                        The Crypt
                    </Link>
                    <span className="px-1.5">/</span>
                    <Link href="/guides" className="hover:text-foreground">
                        Guides
                    </Link>
                    {guide.category && (
                        <>
                            <span className="px-1.5">/</span>
                            <span className="text-foreground">
                                {guide.category}
                            </span>
                        </>
                    )}
                </nav>

                <header className="mb-8 border-b border-border pb-8">
                    <p className="font-mono text-[11px] tracking-widest text-terminal-dim uppercase">
                        Guide{guide.category ? ` · ${guide.category}` : ''}
                    </p>
                    <h1 className="mt-2 text-3xl font-semibold tracking-tight text-foreground">
                        {guide.title}
                    </h1>
                    {guide.teaser && (
                        <p className="mt-3 text-lg text-muted-foreground">
                            {guide.teaser}
                        </p>
                    )}
                    <p className="mt-4 font-mono text-xs text-muted-foreground">
                        By the calcucrypt team
                        {guide.readingMinutes
                            ? ` · ${guide.readingMinutes} min read`
                            : ''}
                        {published ? ` · Updated ${published}` : ''}
                    </p>
                </header>

                <GuideBody guide={guide} embeds={embeds} />

                <CitedCallout calculator={calloutTool ?? undefined} />

                {links.length > 0 && (
                    <div className="space-y-3 border-t border-border pt-8">
                        {links.map((calculator) => (
                            <LinkCta
                                key={calculator.slug}
                                calculator={calculator}
                            />
                        ))}
                    </div>
                )}
            </article>
        </>
    );
}
