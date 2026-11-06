<?php

namespace App\Data;

use App\Models\Post;
use Illuminate\Database\Eloquent\Builder;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Splicewire\Beam\Particle\Attributes\ParticleResource;

/**
 * The read projection for the `guides` particle resource, and its declaration site: every
 * published Guide, addressed by slug, readable by anyone and writable by no one.
 *
 * `bodyHtml` is the cached, block-aware body. It may hold embed markers
 * (`<div data-embed="{anchor}"></div>`) that the Guide page splices a live calculator into.
 */
#[ParticleResource(
    key: 'guides',
    backing: Post::class,
    data: GuideData::class,
    input: false,
    publicRead: GuideData::class,
    includes: ['category'],
    readOnly: true,
    routeKey: 'slug',
)]
#[Title('Guide')]
#[TypeScript]
class GuideData extends Data
{
    public function __construct(
        public string $slug,
        public string $title,
        public ?string $teaser,
        public ?string $bodyHtml,
        public ?string $category,
        public ?string $publishedAt,
    ) {}

    /**
     * Only published guides exist on this resource: a draft or a scheduled one is a 404.
     *
     * @param  Builder<Post>  $query
     * @return Builder<Post>
     */
    public static function scope(Builder $query): Builder
    {
        return $query->published();
    }

    public static function project(Post $post): self
    {
        return new self(
            slug: $post->slug,
            title: $post->title,
            teaser: $post->teaser,
            bodyHtml: $post->displayBody,
            category: $post->category?->name,
            publishedAt: $post->published_at?->toIso8601String(),
        );
    }
}
