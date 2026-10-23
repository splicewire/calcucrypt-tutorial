<?php

use App\Http\Controllers\LocalMarketplaceController;
use App\Http\Middleware\RequireMarketplaceTeam;
use Illuminate\Support\Facades\Route;
use Splicewire\Beam\Authorization\SeatGate;
use Splicewire\Beam\Facades\Particle;
use Splicewire\Beam\Market\Creator\Ops\PublishRelease;
use Splicewire\Beam\Market\Creator\Ops\SubmitListing;
use Splicewire\Beam\Market\Creator\Ops\WithdrawListing;
use Splicewire\Beam\Market\Extensions\Ops\InstallExtension;
use Splicewire\Beam\Market\Extensions\Ops\PurchaseExtension;
use Splicewire\Beam\Market\Extensions\Ops\RefreshInstalledExtension;
use Splicewire\Beam\Market\Extensions\Ops\RemoveInstalledExtension;
use Splicewire\Beam\Market\Extensions\Ops\RevokeBuyerEntitlement;
use Splicewire\Beam\Market\Http\Controllers\ConnectionStatusController;
use Splicewire\Beam\Market\Http\Controllers\Operator\ListingReviewController;
use Splicewire\Beam\Market\RepoAuthorization\Ops\InspectRepoArtifact;
use Splicewire\Beam\Market\RepoAuthorization\Ops\SimulateSellerRepoProviderCallback;
use Splicewire\Beam\Market\Review\Ops\ApproveListingReview;
use Splicewire\Beam\Market\Review\Ops\RejectListingReview;

if (! config('local-marketplace.enabled') || ! class_exists(InstallExtension::class)) {
    return;
}

Route::middleware(['auth', 'verified', RequireMarketplaceTeam::class])->group(function (): void {
    Route::get('local-marketplace', LocalMarketplaceController::class)
        ->name('local-marketplace')->defaults(SeatGate::OPEN_TO_MEMBERS, true);

    Route::prefix('api/local-marketplace')->group(function (): void {
        Particle::mount('sellers', 'market-sellers')->only(['index']);
        Particle::mount('repositories', 'seller-repo-authorizations')->only(['index', 'store'])
            ->ops([InspectRepoArtifact::class, SimulateSellerRepoProviderCallback::class]);
        Particle::mount('listings', 'market-listings')->only(['index', 'show', 'store', 'update'])
            ->ops([SubmitListing::class, PublishRelease::class, WithdrawListing::class]);

        // Installed is a site-wide manifest; only explicit host-operators may mutate it.
        Particle::mount('installed', 'installed-extensions')->only(['index'])
            ->ops([RefreshInstalledExtension::class, RemoveInstalledExtension::class]);
        Particle::mount('catalog', 'market-extensions')->only(['index', 'show'])
            ->ops([InstallExtension::class, PurchaseExtension::class, RevokeBuyerEntitlement::class]);
        Route::get('connection', [ConnectionStatusController::class, 'show'])
            ->name('local-marketplace.connection');
    });
});

Route::middleware(['auth', 'verified', 'require.root'])->prefix('api/local-marketplace/review')
    ->name('local-marketplace.review.')->group(function (): void {
        Route::get('pending', [ListingReviewController::class, 'index'])->name('pending');
        Particle::ops('market-products', 'market-products', [ApproveListingReview::class, RejectListingReview::class]);
    });
