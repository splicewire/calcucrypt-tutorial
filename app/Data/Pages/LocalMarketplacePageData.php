<?php

namespace App\Data\Pages;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class LocalMarketplacePageData extends Data
{
    public function __construct(
        public string $cacheScope,
        public bool $creator,
        public bool $hostOperator,
        public bool $reviewer,
        public bool $simulatedCallback,
        public bool $buyer,
        public bool $paymentSandbox,
        /** @var BuyerEntitlementInspectionData[] */
        public array $buyerEntitlements,
    ) {}
}
