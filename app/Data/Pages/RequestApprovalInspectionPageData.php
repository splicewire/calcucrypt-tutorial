<?php

namespace App\Data\Pages;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class RequestApprovalInspectionPageData extends Data
{
    public function __construct(
        public int $requestId,
        public string $cacheScope,
        public string $backHref,
    ) {}
}
