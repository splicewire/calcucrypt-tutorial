<?php

namespace App\Content\Guides;

use Spatie\LaravelData\Data;

class GuideAssociationData extends Data
{
    public function __construct(public string $slug, public string $mode, public ?string $anchor = null) {}
}
