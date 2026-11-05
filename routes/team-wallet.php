<?php

use App\Providers\TeamApprovalWalletServiceProvider;
use App\Wallet\FundTeamApprovalWallet;
use Illuminate\Support\Facades\Route;
use Splicewire\Beam\Facades\Particle;

if (TeamApprovalWalletServiceProvider::available()) {
    Route::middleware(['auth', 'verified'])->group(function (): void {
        // Literal collection operation precedes every wildcard record/console route.
        Particle::ops('team-approval-wallet', 'team-approval-wallet', [FundTeamApprovalWallet::class]);
    });
}
