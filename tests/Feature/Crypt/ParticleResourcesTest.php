<?php

use App\Data\CalculatorSpecData;
use App\Enums\CalculatorStatus;
use App\Enums\PostStatus;
use App\Models\Calculator;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\CryptSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Splicewire\Beam\Particle\ParticleResourceRegistry;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(CryptSeeder::class);
});

it('declares calculators and guides as read-only particle resources keyed by slug', function (): void {
    $registry = app(ParticleResourceRegistry::class);

    foreach (['calculators', 'guides'] as $key) {
        $resource = $registry->get($key);

        expect($resource->routeKey)->toBe('slug')
            ->and($resource->publicRead)->toBe($resource->data)
            ->and($resource->readOnly)->toBeTrue();
    }
});

it('lets a guest list and read published calculators', function (): void {
    $this->getJson('/api/calculators')
        ->assertOk()
        ->assertJsonCount(4, 'data');

    $this->getJson('/api/calculators/crypto-profit')
        ->assertOk()
        ->assertJsonPath('data.slug', 'crypto-profit')
        ->assertJsonPath('data.category', 'Profit')
        ->assertJsonStructure(['data' => ['graph' => ['nodes', 'outputs']]]);
});

it('lets a guest list and read published guides', function (): void {
    $slug = Post::query()->published()->value('slug');

    $this->getJson('/api/guides')
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $this->getJson("/api/guides/{$slug}")
        ->assertOk()
        ->assertJsonPath('data.slug', $slug)
        ->assertJsonStructure(['data' => ['bodyHtml']]);
});

it('hides drafts from the api and the pages alike', function (): void {
    Calculator::query()->where('slug', 'crypto-profit')->update(['status' => CalculatorStatus::Draft]);
    $guide = Post::query()->published()->firstOrFail();
    $guide->update(['status' => PostStatus::Draft]);

    $this->getJson('/api/calculators/crypto-profit')->assertNotFound();
    $this->getJson('/api/calculators')->assertJsonCount(3, 'data');
    $this->get('/calculators/crypto-profit')->assertNotFound();

    $this->getJson("/api/guides/{$guide->slug}")->assertNotFound();
    $this->getJson('/api/guides')->assertJsonCount(1, 'data');
    $this->get("/guides/{$guide->slug}")->assertNotFound();
});

it('serves a signed-in reader the same projection', function (): void {
    $this->actingAs(User::factory()->create());

    $this->getJson('/api/calculators')->assertOk()->assertJsonCount(4, 'data');
    $this->getJson('/api/calculators/crypto-profit')->assertOk()->assertJsonPath('data.slug', 'crypto-profit');
    $this->getJson('/api/guides')->assertOk()->assertJsonCount(2, 'data');
});

it('accepts no writes', function (): void {
    $this->postJson('/api/calculators', ['name' => 'Nope'])->assertStatus(405);
    $this->postJson('/api/guides', ['title' => 'Nope'])->assertStatus(405);
});

it('renders the tool page from the resource projection', function (): void {
    $this->get('/calculators/crypto-profit')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('calculators/show')
            ->where('spec', CalculatorSpecData::project(Calculator::query()->where('slug', 'crypto-profit')->firstOrFail())->toArray())
            ->has('resolution.seedEnv'),
        );
});
