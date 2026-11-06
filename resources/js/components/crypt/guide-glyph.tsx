import { ArrowLeftRight, BookOpen, Coins, PieChart, Receipt, Repeat, TrendingUp, type LucideIcon } from 'lucide-react';

// A generated, on-brand visual for a Guide card: a deterministic dot-matrix "crypt glyph"
// hashed from the slug (never random), in terminal green, with the category's icon as a faint
// watermark. Theme-aware via the terminal CSS variables; purely decorative (aria-hidden).

const ICONS: Record<string, LucideIcon> = {
    profit: TrendingUp,
    staking: Coins,
    dca: Repeat,
    converter: ArrowLeftRight,
    tax: Receipt,
    allocation: PieChart,
};

const COLS = 5;
const ROWS = 7;
const HALF = Math.ceil(COLS / 2); // mirror columns for a symmetric glyph

// FNV-1a: a small, stable string hash — the same slug always yields the same 32-bit seed.
function hashSlug(input: string): number {
    let hash = 0x811c9dc5;
    for (let i = 0; i < input.length; i++) {
        hash ^= input.charCodeAt(i);
        hash = Math.imul(hash, 0x01000193);
    }
    return hash >>> 0;
}

export function GuideGlyph({ slug, category }: { slug: string; category?: string | null }) {
    const Icon = ICONS[(category ?? '').toLowerCase()] ?? BookOpen;
    const seed = hashSlug(slug);

    const cells: boolean[] = [];
    for (let row = 0; row < ROWS; row++) {
        for (let col = 0; col < COLS; col++) {
            const mirrored = col < HALF ? col : COLS - 1 - col;
            const bit = row * HALF + mirrored; // 0..20, within a 32-bit seed
            cells.push(((seed >>> bit) & 1) === 1);
        }
    }

    return (
        <div aria-hidden className="relative h-24 overflow-hidden border-b border-border bg-muted/30">
            <svg
                viewBox={`0 0 ${COLS} ${ROWS}`}
                preserveAspectRatio="xMidYMid meet"
                className="absolute inset-y-0 left-0 h-full p-3 text-terminal opacity-70"
            >
                {cells.map((on, index) =>
                    on ? (
                        <rect
                            key={index}
                            x={(index % COLS) + 0.12}
                            y={Math.floor(index / COLS) + 0.12}
                            width={0.76}
                            height={0.76}
                            rx={0.16}
                            fill="currentColor"
                        />
                    ) : null,
                )}
            </svg>
            <Icon className="absolute right-3 bottom-2 h-10 w-10 text-terminal-dim opacity-20" strokeWidth={1.5} />
        </div>
    );
}
