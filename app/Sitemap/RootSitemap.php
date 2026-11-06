<?php

namespace App\Sitemap;

/**
 * The starter's worked Frame example — it teaches the record→project pattern by BEING it.
 *
 * A composition of ordered sections; each section holds one or more pluggable leaves that
 * are either kind C ({@see ContentGlobLeaf}, derived docs — the default) or kind A
 * ({@see RecordLeaf}, an owner-edited record). Rendering a section resolves every leaf to
 * a flat NavItem list and orders it by `NavItem::$order`.
 *
 * ── The three starter-packaging dials, as built (see ticket 09) ─────────────────────────
 *  1. trunk-vs-branch:   the RootSitemap lives at the TRUNK (app root) — it is the app's
 *                        whole nav chrome, not one section's sub-nav.
 *  2. full-record vs escape-hatch:  DEFAULT is derived-nav (all-C); the record leaf is an
 *                        OPT-IN escape hatch a clone plugs into a section only when it wants
 *                        owner-editable chrome (+ off-host links). Not a full-record nav.
 *  3. runtime-merge:     the record projection merges into the trunk at RUNTIME, cache-busted
 *                        on save (see {@see RecordLeaf}) — never at build.
 *
 * `default()` returns the ALL-C starter nav: no RecordLeaf, so no DB/record lookup. A clone
 * opts in with `->withRecordLeaf('resources')` (or by adding a RecordLeaf to any section).
 */
class RootSitemap
{
    /** @var list<array{key: string, label: string, leaves: list<SitemapLeaf>}> */
    private array $sections = [];

    /**
     * The default starter nav — ordered sections, every leaf kind C (derived docs). A
     * minimal clone renders this with NO runtime record lookup (dial #2). The doc lists are
     * illustrative stand-ins for the beam-mdx build-time content glob.
     */
    public static function default(): self
    {
        return (new self)
            ->section('build', 'Building', new ContentGlobLeaf([
                ['label' => 'Beam overview', 'slug' => 'build/beam-overview', 'order' => 0],
                ['label' => 'Setup', 'slug' => 'build/setup', 'order' => 1],
                ['label' => 'The schema loop', 'slug' => 'build/schema-loop', 'order' => 2],
                ['label' => 'Frame', 'slug' => 'build/frame', 'order' => 3],
            ]))
            ->section('using', 'Using', new ContentGlobLeaf([
                ['label' => 'Concepts', 'slug' => 'using/concepts', 'order' => 0],
                ['label' => 'API reference', 'slug' => 'using/api', 'order' => 1],
            ]));
    }

    /**
     * Append a section with one or more leaves (each kind C or A).
     */
    public function section(string $key, string $label, SitemapLeaf ...$leaves): self
    {
        $this->sections[] = [
            'key' => $key,
            'label' => $label,
            'leaves' => array_values($leaves),
        ];

        return $this;
    }

    /**
     * Opt into owner-editable chrome: plug the record leaf (kind A) into a section. This is
     * the ONE line a clone adds to turn on the DB-backed, Frame-editable, off-host-capable
     * nav. Absent this call, the sitemap stays all-C (no record lookup).
     */
    public function withRecordLeaf(string $sectionKey = 'links', string $label = 'Links'): self
    {
        return $this->section($sectionKey, $label, new RecordLeaf);
    }

    /**
     * Resolve the whole tree: each section's leaves flattened + ordered into NavItems.
     *
     * @return list<array{key: string, label: string, items: list<NavItem>}>
     */
    public function build(): array
    {
        return array_map(function (array $section): array {
            $items = [];

            foreach ($section['leaves'] as $leaf) {
                array_push($items, ...$leaf->items());
            }

            usort($items, fn (NavItem $a, NavItem $b): int => $a->order <=> $b->order);

            return [
                'key' => $section['key'],
                'label' => $section['label'],
                'items' => $items,
            ];
        }, $this->sections);
    }

    /**
     * True when no section carries a {@see RecordLeaf} — i.e. the pure derived-docs default
     * that pays no runtime record cost. The doctor + tests assert on this.
     */
    public function isAllContentDerived(): bool
    {
        foreach ($this->sections as $section) {
            foreach ($section['leaves'] as $leaf) {
                if ($leaf instanceof RecordLeaf) {
                    return false;
                }
            }
        }

        return true;
    }
}
