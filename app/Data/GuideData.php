<?php

namespace App\Data;

use App\Models\Guide;
use Illuminate\Database\Eloquent\Builder;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Splicewire\Beam\Particle\Attributes\ParticleResource;

/**
 * The read projection for the `guides` particle resource, and its declaration site: every
 * published Guide, addressed by slug, readable by anyone and writable by no one.
 */
#[ParticleResource(
    key: 'guides',
    backing: Guide::class,
    input: false,
    publicRead: GuideData::class,
    includes: ['category'],
    readOnly: true,
    routeKey: 'slug',
    label: 'Guides',
    singularLabel: 'Guide',
)]
#[Title('Guide')]
#[TypeScript]
class GuideData extends Data
{
    public function __construct(
        public string $slug,
        public string $title,
        public ?string $teaser,
        public string $artifactUrl,
        public string $artifactVersion,
        public int $readingMinutes,
        public ?string $category,
        public ?string $publishedAt,
    ) {}

    /**
     * Only published guides exist on this resource: a draft or a scheduled one is a 404.
     *
     * @param  Builder<Guide>  $query
     * @return Builder<Guide>
     */
    public static function scope(Builder $query): Builder
    {
        return $query->published();
    }

    public static function project(Guide $guide): self
    {
        abort_unless($guide->isPublished(), 404);
        $guide->refresh();

        return new self(
            slug: $guide->slug,
            title: $guide->title,
            teaser: $guide->teaser,
            artifactUrl: route('beam.ux.site.artifact', ['entry' => $guide->entry_id, 'version' => app(\Splicewire\Beam\Ux\Compile\EntryArtifactStore::class)->version($guide->entry)]),
            artifactVersion: app(\Splicewire\Beam\Ux\Compile\EntryArtifactStore::class)->version($guide->entry),
            readingMinutes: max(1, (int) round(str_word_count(strip_tags($guide->search_body)) / 200)),
            category: $guide->category?->name,
            publishedAt: $guide->published_at?->toIso8601String(),
        );
    }
}
