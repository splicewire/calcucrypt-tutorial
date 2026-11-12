import { Link, usePage } from '@inertiajs/react';
import { Moon, Sun } from 'lucide-react';
import { type PropsWithChildren } from 'react';
import { useAppearance } from '@/hooks/use-appearance';

// Supplied public chrome. Each chapter adds navigation only when its page routes exist.

const NAV_LINKS: { href: string; label: string }[] = [
];

// The CALCU·CRYPT wordmark: two-tone LCD text in the 14-segment font — "CALCU" in terminal
// green, "CRYPT" in faint grey — exactly as the prototype (no mark/icon).
function Wordmark() {
    return (
        <Link href="/" className="font-digital text-lg leading-none tracking-[0.02em]" aria-label="calcucrypt home">
            <span className="text-terminal">CALCU</span>
            <span className="text-terminal-dim">CRYPT</span>
        </Link>
    );
}

function ThemeToggle() {
    const { resolvedAppearance, updateAppearance } = useAppearance();
    const isDark = resolvedAppearance === 'dark';

    return (
        <button
            type="button"
            onClick={() => updateAppearance(isDark ? 'light' : 'dark')}
            aria-label={isDark ? 'Switch to light mode' : 'Switch to dark mode'}
            title={isDark ? 'Switch to light mode' : 'Switch to dark mode'}
            className="flex size-8 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
        >
            {isDark ? <Sun className="size-4" /> : <Moon className="size-4" />}
        </button>
    );
}

function Auth() {
    const { auth } = usePage<{ auth: { user: unknown | null } }>().props;

    if (auth?.user) {
        return (
            <Link href="/dashboard" className="rounded-md bg-primary px-4 py-1.5 text-sm font-medium text-primary-foreground transition-opacity hover:opacity-90">
                Dashboard
            </Link>
        );
    }

    // Self-serve signup is disabled (ADR-0005); log in / register are hidden from the nav.
    return null;
}

export default function SiteLayout({ children }: PropsWithChildren) {
    return (
        <div className="flex min-h-screen flex-col bg-background font-sans text-foreground">
            <header className="sticky top-0 z-20 border-b border-border bg-background/85 backdrop-blur-md">
                <div className="mx-auto flex max-w-6xl flex-wrap items-center gap-4 px-4 py-3 sm:px-6 lg:px-8">
                    <Wordmark />

                    <nav className="flex items-center gap-1 text-sm font-medium">
                        {NAV_LINKS.map((item) => (
                            <Link
                                key={item.href}
                                href={item.href}
                                className="rounded-md px-3 py-1.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                            >
                                {item.label}
                            </Link>
                        ))}
                    </nav>

                    <div className="ml-auto flex items-center gap-4">
                        <ThemeToggle />
                        <Auth />
                    </div>

                </div>
            </header>

            <main className="flex-1">{children}</main>

            <footer className="border-t border-border bg-background">
                <div className="mx-auto flex max-w-6xl flex-col gap-2 px-4 py-8 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
                    <span className="font-digital text-base leading-none tracking-[0.02em]">
                        <span className="text-terminal">CALCU</span>
                        <span className="text-terminal-dim">CRYPT</span>
                    </span>
                    <span className="font-mono text-xs">
                        Fixture market data (FACTS_DRIVER) · powered by{' '}
                        <a href="https://splicewire.com" className="text-foreground hover:text-terminal hover:underline">
                            Splicewire
                        </a>
                    </span>
                </div>
            </footer>
        </div>
    );
}
