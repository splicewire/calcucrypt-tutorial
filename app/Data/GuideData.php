<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Post;
use Illuminate\Database\Eloquent\Builder;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Splicewire\Beam\Particle\Attributes\ParticleResource;

/**
 * A Guide for its own page: identity plus the cached, block-aware body HTML. The body may
 * contain embed markers (`<div data-embed="{anchor}"></div>`) that the page splices the
 * live CalculatorWidget into.
 *
 * It is also where the `guides` particle resource is declared: the read projection of every
 * published Guide, addressed by slug, readable by anyone and writable by no one.
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
