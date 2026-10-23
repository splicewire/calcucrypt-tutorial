<?php

namespace Tests\Feature;

use App\Providers\TeamApprovalWalletServiceProvider;
use Illuminate\Support\Facades\Route;
use Splicewire\Beam\Particle\ParticleResourceRegistry;
use Tests\TestCase;

class TeamApprovalWalletOffTest extends TestCase
{
    public function createApplication()
    {
        $app = require \Illuminate\Foundation\Application::inferBasePath().'/bootstrap/app.php';
        $this->traitsUsedByTest = class_uses_recursive(static::class);
        $app->afterBootstrapping(\Illuminate\Foundation\Bootstrap\LoadConfiguration::class, function ($app): void {
            $app['config']->set('team-wallet.enabled', false);
        });
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        return $app;
    }

    public function test_disabled_configuration_has_no_resource_or_funding_route(): void
    {
        $this->assertFalse((bool) config('team-wallet.enabled'));
        $this->assertFalse(TeamApprovalWalletServiceProvider::available());
        $this->assertFalse(app(ParticleResourceRegistry::class)->has('team-approval-wallet'));
        foreach (Route::getRoutes() as $route) {
            $this->assertFalse(str_contains($route->uri(), 'team-approval-wallet'));
        }
    }
}
