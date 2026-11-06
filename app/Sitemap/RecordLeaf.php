<?php

namespace App\Sitemap;

use App\Models\SitemapRecord;
use Illuminate\Support\Facades\Cache;
use Splicewire\Beam\Ux\Nav\Contracts\NavItemSource;
use Splicewire\Beam\Ux\Nav\NavItem;

/**
 * Kind A — the opt-in record leaf. Projects the owner-edited {@see SitemapRecord} rows
 * into NavItems via their `extract()` projection. This is the ONLY leaf that touches the
 * database, so a clone that never opts in (never adds this leaf to a section) pays nothing.
 *
 * Runtime-merge, cache-busted on save (dial #3): the projection is memoised under
 * {@see self::CACHE_KEY}; {@see SitemapRecord::booted()} forgets it on every save/delete,
 * so an owner's Frame edit is reflected on the next request without a rebuild.
 *
 * It is also this host's {@see NavItemSource}, the records the packaged orphaned-nav doctor
 * check validates: {@see navItems()} reads them fresh, never through the cache.
 */
class RecordLeaf implements NavItemSource, SitemapLeaf
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

    public function navItems(): iterable
    {
        return SitemapRecord::query()
            ->get()
            ->map(fn (SitemapRecord $record): NavItem => $record->toNavItem())
            ->all();
    }
}
