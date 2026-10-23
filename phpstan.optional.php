<?php

// Only optional adapters are omitted when their owning package is physically absent.
// Installing that exact composition automatically restores analysis of EVERY adapter below.
// Core service-request policy, CommittedApprovals, ApprovalUsage and team authority stay analysed.
$root = __DIR__;
$excluded = [];
if (! is_file($root.'/vendor/splicewire/laravel-beam-market/src/Models/Product.php')) {
    foreach ([
        'app/Http/Controllers/LocalMarketplaceController.php',
        'app/Marketplace/CreatorListingPolicy.php',
        'app/Marketplace/LocalMarketplaceRouteManifest.php',
        'app/Marketplace/SandboxPayoutGateway.php',
        'app/Marketplace/TeamEntitlementScopeResolver.php',
        'app/Marketplace/TeamSellerResolver.php',
        'app/Providers/LocalMarketplaceServiceProvider.php',
        'routes/local-marketplace.php',
    ] as $path) {
        $excluded[] = $root.'/'.$path;
    }
}
if (! is_file($root.'/vendor/splicewire/service-desk-sla/src/Contracts/ServiceDeskHost.php')) {
    // Existing opt-in SLA host boundary; it implements its optional package's contract.
    $excluded[] = $root.'/app/Support/ServiceDeskSlaHost.php';
}
$ignore = [];
if (! is_file($root.'/vendor/rushing/laravel-commerce/src/Wallets.php')) {
    $excluded[] = $root.'/app/Wallet/FundTeamApprovalWallet.php';
    $excluded[] = $root.'/app/Wallet/TeamApprovalWallet.php';
    // Keep the explicit meter's actual code analysed. Only its unavailable interface diagnostic
    // is ignored on shipping225; no namespace-wide or unmatched error suppression.
    $ignore[] = [
        'identifier' => 'interface.notFound',
        'message' => '#^Class App\\\\Wallet\\\\ApprovalWalletMeter implements unknown interface Rushing\\\\Commerce\\\\Contracts\\\\UsageMeter\.$#',
        'path' => $root.'/app/Wallet/ApprovalWalletMeter.php',
        'count' => 1,
    ];
}

return ['parameters' => ['excludePaths' => ['analyse' => $excluded], 'ignoreErrors' => $ignore]];
