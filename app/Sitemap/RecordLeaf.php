<?php

namespace App\Sitemap;

use App\Models\SitemapRecord;
use Illuminate\Support\Facades\Cache;

/**
 * Kind A — the opt-in record leaf. Projects the owner-edited {@see SitemapRecord} rows
 * into NavItems via their `extract()` projection. This is the ONLY leaf that touches the
 * database, so a clone that never opts in (never adds this leaf to a section) pays nothing.
 *
 * Runtime-merge, cache-busted on save (dial #3): the projection is memoised under
 * {@see self::CACHE_KEY}; {@see SitemapRecord::booted()} forgets it on every save/delete,
 * so an owner's Frame edit is reflected on the next request without a rebuild.
 */
class RecordLeaf implements SitemapLeaf
{
    public const CACHE_KEY = 'sitemap.record-leaf.nav';

    public function items(): array
    {
        return array_values(Cache::rememberForever(self::CACHE_KEY, function (): array {
            return SitemapRecord::query()
                ->orderBy('created_at')
                ->get()
                ->map(fn (SitemapRecord $record): NavItem => $record->toNavItem())
                ->all();
        }));
    }
}
