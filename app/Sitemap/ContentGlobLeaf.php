<?php

namespace App\Sitemap;

/**
 * Kind C — the DEFAULT leaf. Derives its NavItems from a static list of docs (in a real
 * clone this is a glob over `resources/js/content/docs/**` resolved at BUILD, mirroring
 * the beam-mdx content map). It never touches the database and never reads a record, so a
 * minimal clone's nav renders with zero runtime cost — the load-bearing property of the
 * all-C default (dial #2 rationale).
 *
 * A content-glob leaf can only address in-repo slugs; it structurally cannot express an
 * off-host link. That gap is exactly what the record leaf (kind A) exists to fill.
 *
 * @param  list<array{label: string, slug: string, order?: int}>  $docs
 */
class ContentGlobLeaf implements SitemapLeaf
{
    /**
     * @param  list<array{label: string, slug: string, order?: int}>  $docs
     */
    public function __construct(
        private array $docs,
        private string $basePath = '/docs',
    ) {}

    public function items(): array
    {
        return array_map(
            fn (array $doc): NavItem => new NavItem(
                label: $doc['label'],
                href: rtrim($this->basePath, '/').'/'.ltrim($doc['slug'], '/'),
                order: $doc['order'] ?? 0,
                external: false,
            ),
            $this->docs,
        );
    }
}
