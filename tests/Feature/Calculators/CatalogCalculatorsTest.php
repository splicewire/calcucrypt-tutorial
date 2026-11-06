<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\CryptSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Splicewire\Beam\Accounts\Teams\TeamProvisioner;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(CryptSeeder::class);
});

// The tutorial's subset of Calcucrypt's catalogue. Its prices come from the keyless
// FixtureConnector (config/facts.php), so every fact's source reads `Fixture`.
dataset('grounded calculators', [
    'profit' => ['crypto-profit', 'profit', ['sellPrice']],
    'converter' => ['crypto-converter', 'converter', ['fromPrice', 'toPrice']],
    // Every row is read as a pro-team Owner (below): a guest would see this one locked.
    'position size' => ['crypto-position-size', 'position-size', ['spot']],
]);

/** The Owner of a pro-plan team, which unlocks every paid calculator. */
function proOwner(): User
{
    $owner = User::factory()->create();
    app(TeamProvisioner::class)->createTeamFor($owner, 'Pro team')->forceFill(['plan' => 'pro'])->save();

    return $owner->refresh();
}

dataset('ungrounded calculators', [
    'capital gains' => ['crypto-capital-gains', 'capital-gains'],
]);

it('renders a grounded calculator with its live facts resolved', function (string $slug, string $computation, array $factNodes): void {
    $this->actingAs(proOwner())
        ->get("/calculators/{$slug}")
        ->assertOk()
        ->assertInertia(function ($page) use ($slug, $computation, $factNodes) {
            $page->component('calculators/show')
                ->where('spec.slug', $slug)
                ->where('spec.computation', $computation);

            foreach ($factNodes as $node) {
                $page->has("resolution.facts.{$node}")
                    ->where("resolution.facts.{$node}.source", 'Fixture');
            }

            return $page;
        });
})->with('grounded calculators');

it('renders an ungrounded tax calculator that resolves with no live facts', function (string $slug, string $computation): void {
    // The tax calculators are on the pro plan; a guest would see them locked.
    $this->actingAs(proOwner())
        ->get("/calculators/{$slug}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('calculators/show')
            ->where('spec.computation', $computation)
            ->where('resolution.facts', []),
        );
})->with('ungrounded calculators');
