<?php

namespace App\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
#[Title('Request approval history')]
final class ReadRequestApprovalHistoryOutputData extends Data
{
    /** @param list<RequestApprovalFactData> $facts */
    public function __construct(
        public int $requestId,
        public string $title,
        public string $currentApproval,
        public ?string $currentPin,
        #[DataCollectionOf(RequestApprovalFactData::class)]
        public array $facts,
    ) {}
}
