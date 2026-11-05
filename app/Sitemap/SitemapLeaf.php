<?php

namespace App\Sitemap;

/**
 * A pluggable leaf in a {@see RootSitemap} section. The composition is closed over
 * exactly two implementations:
 *
 *   - {@see ContentGlobLeaf}  (kind C) — derives NavItems from a doc glob. The DEFAULT.
 *                             Zero runtime cost: no DB, no record lookup, structurally
 *                             cannot express an off-host link.
 *   - {@see RecordLeaf}       (kind A) — projects an owner-edited SitemapRecord into
 *                             NavItems (label/href/order + a cross-host external link).
 *                             The opt-in escape hatch; the only leaf that touches the DB.
 *
 * A leaf resolves to a flat list of NavItems; the RootSitemap orders + concatenates them.
 */
interface SitemapLeaf
{
    /** @return list<NavItem> */
    public function items(): array;
}
