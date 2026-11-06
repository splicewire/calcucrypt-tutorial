<?php

declare(strict_types=1);

namespace App\Content\Calculators;

/**
 * The closed list of Domains (CONTEXT.md) — the discipline a Calculator's computation belongs to, and
 * the first segment of its Calculator Key. Decided by what the computation *is*, never by its crypto
 * framing or the Connector that feeds it: a compound-growth tool on a crypto page is `finance`.
 *
 * A case is added when a batch of the library needs it (math-library-revenue 01 Q6). A published
 * domain is never renamed, because keys travel where no migration reaches. No domain is a single
 * letter: `u` is reserved for user compositions, which resolve through their own store and never
 * through the calculator registry (01 Q9, 09 Q2).
 */
enum Domain: string
{
    case Crypto = 'crypto';
    case Finance = 'finance';

    /** The first segment that is not a domain: user compositions live under it, outside this registry. */
    public const COMPOSITIONS = 'u';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $domain): string => $domain->value, self::cases());
    }
}
