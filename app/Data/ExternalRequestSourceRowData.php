<?php

namespace App\Data;

use Spatie\LaravelData\Attributes\Validation\DateFormat;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Regex;
use Spatie\LaravelData\Data;

class ExternalRequestSourceRowData extends Data
{
    public function __construct(
        #[Regex('/^[A-Za-z0-9-]{1,64}$/')]
        public string $externalId,
        #[Max(200)]
        public string $title,
        #[In('open', 'closed')]
        public string $status,
        #[DateFormat('Y-m-d\TH:i:s\Z')]
        public string $updatedAt,
    ) {}
}
