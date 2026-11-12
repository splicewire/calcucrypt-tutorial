<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Splicewire\Beam\Entitlements\EntitlementKeyUniverse;
use Tests\Support\FakeConnector;

uses(Tests\TestCase::class, RefreshDatabase::class);

// Lock first: the paid calculators carry a declared entitlement key, and no plan holds it yet.
const PAID_CALCULATORS = ['crypto-position-size', 'crypto-capital-gains'];
const FREE_CALCULATORS = ['crypto-profit', 'crypto-converter'];

beforeEach(function (): void {
    config()->set('facts.connectors.coinmarketcap', FakeConnector::class);
    $this->withoutVite();
    $this->artisan('content:sync')->assertSuccessful();
});

it('declares each paid calculator\'s key, which defines its ability and grants it to no one', function (): void {
    $universe = app(EntitlementKeyUniverse::class);

    foreach (PAID_CALCULATORS as $slug) {
        expect($universe->knows("calculators.{$slug}"))->toBeTrue()
            ->and(Gate::has("entitlement:calculators.{$slug}"))->toBeTrue()
            ->and(Gate::forUser(User::factory()->create())->allows("entitlement:calculators.{$slug}"))->toBeFalse();
    }
});

it('locks every paid calculator for a guest and for a signed-in reader alike', function (?bool $signedIn): void {
    if ($signedIn) {
        $this->actingAs(User::factory()->create());
    }

    foreach (PAID_CALCULATORS as $slug) {
        $this->getJson("/api/calculators/{$slug}")
            ->assertOk()
            ->assertJsonPath('data.locked', true)
            ->assertJsonPath('data.graph', null)
            // Now a plan sells it (GatedCalculatorsTest names which); a reader with no plan is still locked out.
            ->assertJsonPath('data.requiredPlan', fn (?string $plan): bool => in_array($plan, ['trader', 'pro'], true));

        $this->postJson("/api/calculators/{$slug}/resolve")->assertForbidden();
    }

    foreach (FREE_CALCULATORS as $slug) {
        $this->getJson("/api/calculators/{$slug}")->assertOk()->assertJsonPath('data.locked', false);
        $this->postJson("/api/calculators/{$slug}/resolve")->assertOk();
    }
})->with(['guest' => [false], 'signed in' => [true]]);

it('describes a locked calculator on its tool page but sends no math and no prices', function (): void {
    $this->get('/calculators/crypto-capital-gains')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('calculators/show')
            ->where('spec.locked', true)
            ->where('spec.graph', null)
            ->where('resolution', null),
        );

    $this->get('/calculators')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('calculators', fn ($calculators) => collect($calculators)->where('locked', true)->count() === 2));
});
