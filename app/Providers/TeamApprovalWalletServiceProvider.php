<?php

namespace App\Providers;

use App\Wallet\Data\TeamApprovalWalletData;
use App\Wallet\TeamWalletContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Rushing\Commerce\Wallets;
use Splicewire\Beam\Particle\ParticleResourceRegistry;

final class TeamApprovalWalletServiceProvider extends ServiceProvider
{
    public static function available(): bool
    {
        return (bool) config('team-wallet.enabled') && class_exists(Wallets::class);
    }

    public function boot(): void
    {
        if (! self::available()) {
            return;
        }
        Gate::define('service-desk.wallet.read', fn ($actor): bool => app(TeamWalletContext::class)->team($actor) !== null);
        Gate::define('entitlement:service-desk.wallet.fund', fn ($actor): bool => app(TeamWalletContext::class)->mayFund($actor));
        app(ParticleResourceRegistry::class)->registerClass(TeamApprovalWalletData::class, ['tenant']);
        config(['frame.realms.tenant' => [...config('frame.realms.tenant', []), 'team-approval-wallet']]);
    }
}
