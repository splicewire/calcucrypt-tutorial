<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Guide;
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

    public static function fromModel(Guide $guide): self
    {
        abort_unless($guide->isPublished(), 404);
        $guide->refresh();

        return new self(
            slug: $guide->slug,
            title: $guide->title,
            teaser: $guide->teaser,
            category: $guide->category?->name,
        );
    }
}
