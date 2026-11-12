<?php

use App\Models\User;
use Database\Seeders\CryptSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Splicewire\Beam\Models\BeamParticle;
use Splicewire\Beam\Particle\Backing\ActorOwnedParticleAccess;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->savedInputSchemas = sys_get_temp_dir().'/saved-input-schemas-'.Illuminate\Support\Str::uuid();
    app()->instance(Schemastud\DataSchemas\Contracts\SchemaRegistry::class,
        new Schemastud\DataSchemas\Lifecycle\FilesystemSchemaRegistry($this->savedInputSchemas));
    $this->seed(CryptSeeder::class);
});

afterEach(function (): void {
    Illuminate\Support\Facades\File::deleteDirectory($this->savedInputSchemas);
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

    expect(Illuminate\Support\Facades\Schema::hasTable('saved_inputs'))->toBeFalse();
    expect(ActorOwnedParticleAccess::owns($reader, BeamParticle::query()->findOrFail($id)))->toBeTrue();

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

    expect(BeamParticle::query()->findOrFail($theirs)->payload['name'])->toBe('My usual ETH trade');
});

it('validates a save against its declared input', function (): void {
    $this->actingAs(User::factory()->create());

    $this->postJson('/api/saved-inputs', saveInputs(['calculator' => 'no-such-calculator']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('calculator');

    $this->postJson('/api/saved-inputs', ['name' => 'No calculator'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['calculator', 'inputs']);

    expect(BeamParticle::query()->where('schema_ref', App\Data\SavedInputData::class)->count())->toBe(0);
});

it('preserves an empty named input map on update', function (): void {
    $this->actingAs(User::factory()->create());
    $id = $this->postJson('/api/saved-inputs', saveInputs())->assertCreated()->json('data.id');
    $response = $this->putJson('/api/saved-inputs/'.$id, ['inputs' => (object) []])->assertOk();
    $this->assertInstanceOf(stdClass::class, json_decode($response->getContent())->data->inputs);
    $detail = $this->getJson('/api/saved-inputs/'.$id)->assertOk();
    $this->assertInstanceOf(stdClass::class, json_decode($detail->getContent())->data->inputs);
    $list = $this->getJson('/api/saved-inputs')->assertOk();
    $this->assertInstanceOf(stdClass::class, json_decode($list->getContent())->data[0]->inputs);
});

it('preserves named numeric and string maps through actual Frame CRUD and detail prefill', function (): void {
    $this->actingAs(User::factory()->create());
    $created = $this->postJson('/frame/resources/saved-inputs', saveInputs())->assertOk()
        ->assertJsonPath('data.calculator', 'crypto-profit')->assertJsonPath('data.inputs.amount', 2);
    $this->assertInstanceOf(stdClass::class, json_decode($created->getContent())->data->inputs);
    $id = $created->json('data.id');
    $this->getJson('/frame/resources/saved-inputs')->assertOk()->assertJsonPath('data.0.inputs.asset', 'ETH');
    $this->getJson('/frame/resources/saved-inputs/records/'.$id)->assertOk()
        ->assertJsonPath('data.calculator', 'crypto-profit')->assertJsonPath('data.inputs.amount', 2);
    $updated = $this->putJson('/frame/resources/saved-inputs/records/'.$id, ['inputs' => (object) []])->assertOk();
    $this->assertInstanceOf(stdClass::class, json_decode($updated->getContent())->data->inputs);
    $detail = $this->getJson('/frame/resources/saved-inputs/records/'.$id)->assertOk();
    $this->assertInstanceOf(stdClass::class, json_decode($detail->getContent())->data->inputs);
    $list = $this->getJson('/frame/resources/saved-inputs')->assertOk();
    $this->assertInstanceOf(stdClass::class, json_decode($list->getContent())->data[0]->inputs);
    $this->deleteJson('/frame/resources/saved-inputs/records/'.$id)->assertSuccessful();
});

it('uses stable calculator references for rename and normal calculator or owner deletion', function (): void {
    $owner = User::factory()->create();
    $this->actingAs($owner);
    $id = $this->postJson('/api/saved-inputs', saveInputs())->assertCreated()->json('data.id');
    $calculator = App\Models\Calculator::query()->where('slug', 'crypto-profit')->firstOrFail();
    expect(BeamParticle::findOrFail($id)->payload['calculator'])->toBe($calculator->id);
    $calculator->update(['slug' => 'renamed-profit']);
    $this->getJson('/api/saved-inputs/'.$id)->assertOk()->assertJsonPath('data.calculator', 'renamed-profit');
    $this->getJson('/frame/resources/saved-inputs/records/'.$id)->assertOk()->assertJsonPath('data.calculator', 'renamed-profit');
    $calculator->delete();
    expect(BeamParticle::find($id))->toBeNull();

    $otherCalculator = App\Models\Calculator::query()->published()->firstOrFail();
    $secondId = $this->postJson('/api/saved-inputs', saveInputs(['calculator' => $otherCalculator->slug]))->assertCreated()->json('data.id');
    $owner->delete();
    expect(BeamParticle::find($secondId))->toBeNull();
});

it('rejects invalid map values, lists, envelope forgery and unpublished calculators on both transports', function (): void {
    $this->actingAs(User::factory()->create());
    foreach (['/api/saved-inputs', '/frame/resources/saved-inputs'] as $uri) {
        foreach ([['bad' => true], ['bad' => null], ['bad' => ['nested']], ['list']] as $inputs) {
            $this->postJson($uri, saveInputs(['inputs' => $inputs]))->assertUnprocessable();
        }
        foreach (['owner', 'meta', 'schema_ref', 'schema_id', 'source_tier', 'id'] as $field) {
            $this->postJson($uri, [...saveInputs(), $field => 'forged'])->assertUnprocessable();
        }
    }
    $calculator = App\Models\Calculator::query()->where('slug', 'crypto-profit')->firstOrFail();
    $calculator->update(['status' => App\Enums\CalculatorStatus::Draft]);
    foreach (['/api/saved-inputs', '/frame/resources/saved-inputs'] as $uri) {
        $this->postJson($uri, saveInputs())->assertUnprocessable()->assertJsonValidationErrors('calculator');
    }
    expect(BeamParticle::query()->where('schema_ref', App\Data\SavedInputData::class)->count())->toBe(0);
});

it('retains omitted fields and scopes every Frame verb by owner and schema', function (): void {
    $this->actingAs($owner = User::factory()->create());
    $id = $this->postJson('/frame/resources/saved-inputs', saveInputs())->assertOk()->json('data.id');
    $this->putJson('/frame/resources/saved-inputs/records/'.$id, ['name' => 'Only name'])
        ->assertOk()->assertJsonPath('data.calculator', 'crypto-profit')->assertJsonPath('data.inputs.amount', 2);
    $before = BeamParticle::findOrFail($id)->getRawOriginal();
    $this->actingAs(User::factory()->create());
    $this->getJson('/frame/resources/saved-inputs')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/frame/resources/saved-inputs/records/'.$id)->assertNotFound();
    $this->putJson('/frame/resources/saved-inputs/records/'.$id, ['name' => 'Taken'])->assertNotFound();
    $this->deleteJson('/frame/resources/saved-inputs/records/'.$id)->assertNotFound();
    expect(BeamParticle::findOrFail($id)->getRawOriginal())->toEqual($before);
    $this->actingAs($owner);
    $foreign = BeamParticle::create(['schema_ref' => 'another/schema', 'payload' => ['name' => 'Foreign'], 'meta' => ['owner' => ActorOwnedParticleAccess::owner($owner)]]);
    foreach (['/api/saved-inputs/'.$foreign->id, '/frame/resources/saved-inputs/records/'.$foreign->id] as $uri) {
        $this->getJson($uri)->assertNotFound();
        $this->putJson($uri, ['name' => 'Taken'])->assertNotFound();
        $this->deleteJson($uri)->assertNotFound();
    }
    expect(BeamParticle::query()->whereKey($foreign->id)->exists())->toBeTrue();
});

it('removes every owned association across multiple deletion chunks', function (): void {
    $owner = User::factory()->create();
    $this->actingAs($owner);
    $id = $this->postJson('/api/saved-inputs', saveInputs())->assertCreated()->json('data.id');
    $record = BeamParticle::findOrFail($id);
    $calculator = App\Models\Calculator::whereKey($record->payload['calculator'])->firstOrFail();
    $template = $record->getRawOriginal();
    $table = $record->getTable();
    for ($batch = 0; $batch < 11; $batch++) {
        $rows = [];
        for ($i = 0; $i < 100; $i++) {
            $rows[] = [...$template, 'id' => (string) Illuminate\Support\Str::uuid()];
        }
        Illuminate\Support\Facades\DB::table($table)->insert($rows);
    }
    $calculator->delete();
    expect(BeamParticle::where('schema_ref', App\Data\SavedInputData::class)->count())->toBe(0);
    $calculator = App\Models\Calculator::query()->published()->firstOrFail();
    $template['payload'] = json_encode(['calculator' => $calculator->id, 'name' => 'User deletion', 'inputs' => ['amount' => 1]]);
    for ($batch = 0; $batch < 11; $batch++) {
        $rows = [];
        for ($i = 0; $i < 100; $i++) {
            $rows[] = [...$template, 'id' => (string) Illuminate\Support\Str::uuid()];
        }
        Illuminate\Support\Facades\DB::table($table)->insert($rows);
    }
    $owner->delete();
    expect(BeamParticle::where('schema_ref', App\Data\SavedInputData::class)->count())->toBe(0);
});

it('fails closed on list and detail after unsupported bulk SQL calculator deletion', function (): void {
    $this->actingAs(User::factory()->create());
    $id = $this->postJson('/api/saved-inputs', saveInputs())->assertCreated()->json('data.id');
    Illuminate\Support\Facades\DB::table('calculators')->where('slug', 'crypto-profit')->delete();
    foreach (['/api/saved-inputs', '/api/saved-inputs/'.$id, '/frame/resources/saved-inputs', '/frame/resources/saved-inputs/records/'.$id] as $uri) {
        $this->getJson($uri)->assertNotFound();
    }
    expect(BeamParticle::whereKey($id)->exists())->toBeTrue();
});

it('rejects forged update envelopes without mutation on both transports', function (): void {
    $this->actingAs(User::factory()->create());
    $id = $this->postJson('/api/saved-inputs', saveInputs())->assertCreated()->json('data.id');
    $before = BeamParticle::findOrFail($id)->getRawOriginal();
    foreach (['/api/saved-inputs/'.$id, '/frame/resources/saved-inputs/records/'.$id] as $uri) {
        foreach (['owner', 'meta', 'id', 'schema_ref'] as $field) {
            $this->putJson($uri, ['name' => 'Changed', $field => 'forged'])->assertUnprocessable();
        }
    }
    expect(BeamParticle::findOrFail($id)->getRawOriginal())->toEqual($before);
});

it('reserves magic array creation for stored envelopes and memoizes calculator lookup per request', function (): void {
    expect(fn () => App\Data\SavedInputData::from(['id' => 'wire-id', 'calculator' => 'crypto-profit', 'name' => 'Wire', 'inputs' => [], 'savedAt' => '2025-01-01']))
        ->toThrow(UnexpectedValueException::class);
    $this->actingAs(User::factory()->create());
    $this->postJson('/api/saved-inputs', saveInputs())->assertCreated();
    $this->postJson('/api/saved-inputs', saveInputs(['name' => 'Second']))->assertCreated();
    Illuminate\Support\Facades\DB::enableQueryLog();
    $this->getJson('/api/saved-inputs')->assertOk()->assertJsonCount(2, 'data');
    $lookups = collect(Illuminate\Support\Facades\DB::getQueryLog())->filter(fn ($query) => str_contains($query['query'], 'from "calculators"'));
    Illuminate\Support\Facades\DB::disableQueryLog();
    expect($lookups)->toHaveCount(1);
});
