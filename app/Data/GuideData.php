<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Post;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A Guide for its own page: identity plus the cached, block-aware body HTML. The body may
 * contain embed markers (`<div data-embed="{anchor}"></div>`) that the page splices the
 * live CalculatorWidget into.
 */
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

    public static function fromModel(Post $post): self
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
