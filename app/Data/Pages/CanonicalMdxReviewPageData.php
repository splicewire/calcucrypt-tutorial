<?php

namespace App\Data\Pages;

use App\Data\CanonicalMdxVersionChoiceData;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Splicewire\Beam\Ux\Data\ReleaseData;

#[TypeScript]
final class CanonicalMdxReviewPageData extends Data
{
    /**
     * @param  list<CanonicalMdxVersionChoiceData>  $versions
     * @param  list<ReleaseData>  $releases
     */
    public function __construct(
        public string $entryId,
        public string $title,
        #[DataCollectionOf(CanonicalMdxVersionChoiceData::class)] public array $versions,
        #[DataCollectionOf(ReleaseData::class)] public array $releases,
    ) {}
}
