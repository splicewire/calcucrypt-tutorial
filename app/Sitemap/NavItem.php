<?php

namespace App\Sitemap;

use App\Data\SitemapData;
use App\Models\SitemapRecord;
use Spatie\LaravelData\Data;

/**
 * The single leaf shape the whole nav renders over — the projection target both leaf
 * kinds resolve to. A content-glob leaf (kind C) derives one of these per doc; the
 * record leaf (kind A) projects a {@see SitemapData} into one via
 * {@see SitemapRecord::toNavItem()}. Frame owns no router (see NavMetadata):
 * the host renders its nav by mapping over these, so this is the host's join shape.
 *
 * `external` marks an off-host `href` — the affordance a content-derived nav
 * structurally cannot express (a doc glob only knows in-repo slugs). The record leaf
 * sets it when projecting a {@see SitemapData::$externalUrl}.
 */
class NavItem extends Data
{
    public function __construct(
        public string $label,
        public string $href,
        public int $order = 0,
        public bool $external = false,
    ) {}
}
