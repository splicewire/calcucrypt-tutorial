<?php

namespace App\Data\Pages;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** Root inspection identifiers and payment history; never a license key. */
#[TypeScript]
final class BuyerEntitlementInspectionData extends Data
{
    public function __construct(
        public string $entitlementId,
        public int $productId,
        public string $listing,
        public string $buyerRef,
        public ?string $scopeRef,
        public string $status,
        public ?string $paymentRef,
        public ?string $licenseId,
    ) {}
}
