<?php

namespace App\Data;

use Schemastud\DataSchemas\Attributes\ArrayItems;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class RequestApprovalFactData extends Data
{
    /** @param list<string> $from
     * @param  list<string>  $to
     */
    public function __construct(
        public string $id,
        public string $transition,
        #[ArrayItems('string')]
        public array $from,
        #[ArrayItems('string')]
        public array $to,
        public ?string $actorReference,
        public string $occurredAt,
        public ?string $versionId,
        public string $definitionStatus,
        public ?string $definitionKey,
        public ?string $definitionName,
        public ?int $definitionVersion,
    ) {}
}
