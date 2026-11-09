<?php

use App\Models\SavedInput;
use App\Models\User;
use Database\Seeders\CryptSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(CryptSeeder::class);
});

function saveInputs(array $overrides = []): array
{
    return [
        'calculator' => 'crypto-profit',
        'name' => 'My usual ETH trade',
        'inputs' => ['asset' => 'ETH', 'amount' => 2],
        ...$overrides,
    ];
}

it('mounts all five verbs from the generator defaults', function (): void {
    $names = collect(Route::getRoutes()->getRoutes())->map->getName()->filter(fn ($n) => str_starts_with((string) $n, 'api.saved-inputs.'));

    // The five CRUD verbs, plus the discovery route every mount carries.
    expect($names->sort()->values()->all())->toBe([
        'api.saved-inputs.destroy',
        'api.saved-inputs.discovery',
        'api.saved-inputs.index',
        'api.saved-inputs.show',
        'api.saved-inputs.store',
        'api.saved-inputs.update',
    ]);
});

it('refuses a guest', function (): void {
    // No Public Read is declared, so a guest reads nothing and writes nothing.
    $this->getJson('/api/saved-inputs')->assertForbidden();
    $this->postJson('/api/saved-inputs', saveInputs())->assertForbidden();
});

it('saves, lists, renames and deletes a reader\'s own inputs', function (): void {
    $this->actingAs($reader = User::factory()->create());

    $id = $this->postJson('/api/saved-inputs', saveInputs())
        ->assertCreated()
        ->assertJsonPath('data.calculator', 'crypto-profit')
        ->assertJsonPath('data.inputs.asset', 'ETH')
        ->json('data.id');

    expect(SavedInput::query()->findOrFail($id)->user_id)->toBe($reader->id);

    $this->getJson('/api/saved-inputs')->assertOk()->assertJsonCount(1, 'data');

    $this->putJson("/api/saved-inputs/{$id}", ['name' => 'Renamed'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Renamed')
        ->assertJsonPath('data.inputs.amount', 2);

    $this->deleteJson("/api/saved-inputs/{$id}")->assertSuccessful();
    $this->getJson('/api/saved-inputs')->assertOk()->assertJsonCount(0, 'data');
});

it('keeps each reader to their own', function (): void {
    $this->actingAs(User::factory()->create());
    $theirs = $this->postJson('/api/saved-inputs', saveInputs())->assertCreated()->json('data.id');

    $this->actingAs(User::factory()->create());

    $this->getJson('/api/saved-inputs')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson("/api/saved-inputs/{$theirs}")->assertNotFound();
    $this->putJson("/api/saved-inputs/{$theirs}", ['name' => 'Mine now'])->assertNotFound();
    $this->deleteJson("/api/saved-inputs/{$theirs}")->assertNotFound();

    expect(SavedInput::query()->findOrFail($theirs)->name)->toBe('My usual ETH trade');
});

it('validates a save against its declared input', function (): void {
    $this->actingAs(User::factory()->create());

    $this->postJson('/api/saved-inputs', saveInputs(['calculator' => 'no-such-calculator']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('calculator');

    $this->postJson('/api/saved-inputs', ['name' => 'No calculator'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['calculator', 'inputs']);

    expect(SavedInput::query()->count())->toBe(0);
});
