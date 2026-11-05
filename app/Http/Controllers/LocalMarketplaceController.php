<?php

namespace App\Http\Controllers;

use App\Data\Pages\BuyerEntitlementInspectionData;
use App\Data\Pages\LocalMarketplacePageData;
use App\Marketplace\TeamMarketplaceContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Rushing\LaravelDataSchemasScribe\Attributes\ResponseFromData;
use Splicewire\Beam\Accounts\Facades\BeamAccounts;

final class LocalMarketplaceController
{
    #[ResponseFromData(LocalMarketplacePageData::class)]
    public function __invoke(Request $request, TeamMarketplaceContext $context): Response
    {
        $actor = $request->user();

        return Inertia::render('examples/local-marketplace', new LocalMarketplacePageData(
            cacheScope: $actor->getAuthIdentifier().':'.$context->team($actor)->getKey(),
            creator: $context->mayCreate($actor),
            hostOperator: $context->mayOperate($actor),
            reviewer: BeamAccounts::isRoot($actor),
            simulatedCallback: (bool) config('beam.market.repo_authorization.simulated_callback'),
            buyer: $context->mayPurchase($actor),
            paymentSandbox: (bool) config('local-marketplace.payment_sandbox'),
            buyerEntitlements: BeamAccounts::isRoot($actor) ? \Splicewire\Beam\Market\Models\ListingEntitlement::query()
                ->with('product')->latest('granted_at')->limit(100)->get()->map(fn ($grant) => new BuyerEntitlementInspectionData(
                    entitlementId: (string) $grant->id,
                    productId: (int) $grant->product_id,
                    listing: (string) ($grant->product?->translate('name') ?? 'Unavailable listing'),
                    buyerRef: $grant->buyer_ref,
                    scopeRef: $grant->scope_ref,
                    status: $grant->status,
                    paymentRef: $grant->payment_ref,
                    licenseId: $grant->license_id,
                ))->all() : [],
        ));
    }
}
