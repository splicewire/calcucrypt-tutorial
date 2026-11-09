<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Post;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The card shape for a Guide in browse/search/featured lists.
 */
#[TypeScript]
class GuideSummaryData extends Data
{
    public function __construct(
        public string $slug,
        public string $title,
        public ?string $teaser,
        public ?string $category,
    ) {}

    public static function fromModel(Post $post): self
    {
        return new self(
            slug: $post->slug,
            title: $post->title,
            teaser: $post->teaser,
            category: $post->category?->name,
        );
    }
}
