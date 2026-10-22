<?php

namespace App\Data;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Data;

class ExternalRequestSourceResponseData extends Data
{
    /** @param array<ExternalRequestSourceRowData> $data */
    public function __construct(
        #[Max(100)]
        public array $data,
    ) {}
}
