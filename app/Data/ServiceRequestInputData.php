<?php

namespace App\Data;

use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\Max;
use Splicewire\Beam\Data\BeamData;
use Splicewire\Beam\Write\Contracts\MapsToModelAttributes;

#[Title('Service request')]
class ServiceRequestInputData extends BeamData implements MapsToModelAttributes
{
    public function __construct(
        #[Max(200)]
        public string $title,
        #[In('open', 'closed')]
        public string $status = 'open',
    ) {}

    /** @return array{title: string, status: string} */
    public function toModelAttributes(): array
    {
        return ['title' => $this->title, 'status' => $this->status];
    }
}
