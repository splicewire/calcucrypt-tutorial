<?php

use App\Data\SavedInputData;
use App\Models\Calculator;
use App\Models\User;
use App\SavedInputs\LegacySavedInputsMigration;
use Database\Seeders\CryptSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Schemastud\DataSchemas\Contracts\SchemaRegistry;
use Schemastud\DataSchemas\Lifecycle\FilesystemSchemaRegistry;
use Splicewire\Beam\Events\BeamParticlePersisted;
use Splicewire\Beam\Facades\Beam;
use Splicewire\Beam\Models\BeamParticle;
use Splicewire\Beam\Particle\Backing\ActorOwnedParticleAccess;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->savedInputSchemas = sys_get_temp_dir().'/saved-input-schemas-'.Str::uuid();
    app()->instance(SchemaRegistry::class,
        new FilesystemSchemaRegistry($this->savedInputSchemas));
    $this->seed(CryptSeeder::class);
    config(['app.maintenance.driver' => 'array']);
    // This fixture also represents legacy installations whose foreign keys were disabled.
    Schema::dropIfExists('saved_inputs');
    Schema::create('saved_inputs', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->uuid('user_id');
        $table->uuid('calculator_id');
        $table->string('name');
        $table->json('inputs');
        $table->timestamps();
    });
});

afterEach(function (): void {
    File::deleteDirectory($this->savedInputSchemas);
});

function legacySavedInputRow(array $overrides = []): array
{
    $row = [
        'id' => (string) Str::uuid(), 'user_id' => User::factory()->create()->id,
        'calculator_id' => Calculator::query()->where('slug', 'crypto-profit')->firstOrFail()->id,
        'name' => 'Legacy inputs', 'inputs' => json_encode(['asset' => 'RETIRED_SYMBOL', 'unknown_amount' => 2.5]),
        'created_at' => '2025-01-02 03:04:05', 'updated_at' => '2025-02-03 04:05:06',
        ...$overrides,
    ];
    DB::table('saved_inputs')->insert($row);

    return $row;
}

it('preflights without changing records, schema files, history or events', function (): void {
    legacySavedInputRow();
    Event::fake([BeamParticlePersisted::class]);
    $directory = storage_path('framework/testing/legacy-schemas-'.Str::uuid());
    app()->instance(SchemaRegistry::class, new FilesystemSchemaRegistry($directory));
    $versions = DB::table('beam_versions')->count();
    $revisions = DB::table('activity_log')->count();
    try {
        $this->artisan('calcucrypt:migrate-saved-inputs')->assertExitCode(0);
        expect(BeamParticle::query()->where('schema_ref', SavedInputData::class)->count())->toBe(0);
        expect(DB::table('saved_inputs')->count())->toBe(1);
        expect(File::allFiles($directory))->toBe([]);
        expect(DB::table('beam_versions')->count())->toBe($versions);
        expect(DB::table('activity_log')->count())->toBe($revisions);
        Event::assertNotDispatched(BeamParticlePersisted::class);
    } finally {
        File::deleteDirectory($directory);
    }
});

it('requires maintenance mode for apply and exposes no migration HTTP operation', function (): void {
    legacySavedInputRow();
    $this->artisan('calcucrypt:migrate-saved-inputs --apply')->assertExitCode(1);
    expect(BeamParticle::query()->where('schema_ref', SavedInputData::class)->count())->toBe(0);
    $exposed = fn () => collect(Illuminate\Support\Facades\Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => ($route->defaults[Splicewire\Beam\Http\Particle\ParticleOperationController::NAME] ?? null) === 'migrate-legacy');
    expect($exposed())->toHaveCount(0);
    expect(collect(Illuminate\Support\Facades\Route::getRoutes()->getRoutes())->contains(fn ($route) => str_contains($route->uri(), 'migrate-legacy')))->toBeFalse();
    expect(app(Splicewire\Beam\Frame\ParticleResourceActions::class)->for('saved-inputs'))->toBe([]);
    // A real temporary mount is the red control: the same detector must now find it.
    Splicewire\Beam\Facades\Particle::mount('saved-inputs', 'proof-saved-inputs')->names('proof.saved-inputs')->ops([App\Particle\Operations\MigrateLegacySavedInputs::class])->register();
    expect($exposed())->toHaveCount(1);
});

it('preserves IDs, owners, timestamps and unpublished calculator associations and reruns unchanged', function (): void {
    $first = legacySavedInputRow();
    $second = legacySavedInputRow();
    DB::table('calculators')->where('id', $first['calculator_id'])->update(['status' => 'draft']);
    $source = DB::table('saved_inputs')->orderBy('id')->get()->toArray();
    Event::fake([BeamParticlePersisted::class]);
    app()->maintenanceMode()->activate([]);
    $result = app(LegacySavedInputsMigration::class)->run(true);
    expect($result->created)->toBe(2);
    expect($result->skipped)->toBe(0);
    $record = BeamParticle::query()->findOrFail($first['id']);
    expect($record->payload)->toBe(['calculator' => $first['calculator_id'], 'name' => $first['name'], 'inputs' => ['asset' => 'RETIRED_SYMBOL', 'unknown_amount' => 2.5]]);
    expect(ActorOwnedParticleAccess::owns(User::findOrFail($first['user_id']), $record))->toBeTrue();
    expect($record->created_at->format('Y-m-d H:i:s'))->toBe($first['created_at']);
    expect($record->updated_at->format('Y-m-d H:i:s'))->toBe($first['updated_at']);
    expect($record->getAttribute('head_version'))->toBeNull();
    expect($record->versions()->count())->toBe(0);
    expect($record->revisions())->toBe([]);
    $again = app(LegacySavedInputsMigration::class)->run(true);
    expect($again->created)->toBe(0);
    expect($again->skipped)->toBe(2);
    expect(DB::table('saved_inputs')->orderBy('id')->get()->toArray())->toEqual($source);
    Event::assertNotDispatched(BeamParticlePersisted::class);
    app()->maintenanceMode()->deactivate();
    $this->actingAs(User::findOrFail($first['user_id']))->getJson('/api/saved-inputs/'.$first['id'])
        ->assertOk()->assertJsonPath('data.calculator', 'crypto-profit')
        ->assertJsonPath('data.savedAt', '2025-02-03T04:05:06+00:00');
});

it('refuses a conflicting UUID across schema or owner without overwriting or partially importing', function (): void {
    $first = legacySavedInputRow();
    legacySavedInputRow();
    $conflict = new BeamParticle(['schema_ref' => 'another/schema', 'payload' => ['name' => 'Untouched'], 'meta' => ['owner' => ['type' => User::class, 'id' => 'different']]]);
    $conflict->setAttribute('id', $first['id']);
    $conflict->save();
    $before = BeamParticle::query()->orderBy('id')->get()->map->getRawOriginal()->all();
    app()->maintenanceMode()->activate([]);
    expect(fn () => app(LegacySavedInputsMigration::class)->run(true))->toThrow(ValidationException::class);
    expect(BeamParticle::query()->orderBy('id')->get()->map->getRawOriginal()->all())->toEqual($before);
    expect($conflict->fresh()->payload)->toBe(['name' => 'Untouched']);
    expect(DB::table('saved_inputs')->count())->toBe(2);
});

it('rejects missing owners, missing calculators and malformed JSON or values before any import', function (): void {
    foreach ([['user_id' => (string) Str::uuid()], ['calculator_id' => (string) Str::uuid()], ['inputs' => '{broken'], ['inputs' => json_encode(['bad' => true])]] as $bad) {
        DB::table('saved_inputs')->delete();
        legacySavedInputRow();
        legacySavedInputRow($bad);
        app()->maintenanceMode()->activate([]);
        expect(fn () => app(LegacySavedInputsMigration::class)->run(true))->toThrow(ValidationException::class);
        expect(BeamParticle::query()->where('schema_ref', SavedInputData::class)->count())->toBe(0);
        expect(DB::table('saved_inputs')->count())->toBe(2);
    }
});

it('rolls all particle writes back when the database refuses a later row', function (): void {
    $first = legacySavedInputRow(['id' => '10000000-0000-4000-8000-000000000001']);
    legacySavedInputRow(['id' => '20000000-0000-4000-8000-000000000002']);
    DB::unprepared('CREATE TRIGGER refuse_second_legacy BEFORE INSERT ON '.Beam::table('particles')." WHEN NEW.id = '20000000-0000-4000-8000-000000000002' BEGIN SELECT RAISE(ABORT, 'fixture refuses second row'); END");
    app()->maintenanceMode()->activate([]);
    expect(fn () => app(LegacySavedInputsMigration::class)->run(true))->toThrow(Illuminate\Database\QueryException::class);
    expect(BeamParticle::query()->find($first['id']))->toBeNull();
    expect(BeamParticle::query()->where('schema_ref', SavedInputData::class)->count())->toBe(0);
    expect(DB::table('saved_inputs')->count())->toBe(2);
});

it('distinguishes an absent legacy source from a source with no rows', function (): void {
    $present = app(LegacySavedInputsMigration::class)->run(false);
    expect($present->sourcePresent)->toBeTrue();
    expect($present->examined)->toBe(0);
    Schema::drop('saved_inputs');
    $absent = app(LegacySavedInputsMigration::class)->run(false);
    expect($absent->sourcePresent)->toBeFalse();
    expect($absent->examined)->toBe(0);
});

it('runs real Artisan apply and tolerates database JSON object key reordering', function (): void {
    $row = legacySavedInputRow();
    app()->maintenanceMode()->activate([]);
    $this->artisan('calcucrypt:migrate-saved-inputs --apply')->expectsOutput((new App\Data\MigrateLegacySavedInputsOutputData(false, true, 1, 1, 0, 0))->toJson())->assertExitCode(0);
    $particle = BeamParticle::findOrFail($row['id']);
    $payload = $particle->payload;
    $payload['inputs'] = array_reverse($payload['inputs'], true);
    $particle->setAttribute('payload', array_reverse($payload, true));
    $particle->timestamps = false;
    $particle->save();
    $this->artisan('calcucrypt:migrate-saved-inputs --apply')->expectsOutput((new App\Data\MigrateLegacySavedInputsOutputData(false, true, 1, 0, 0, 1))->toJson())->assertExitCode(0);
});

it('refuses each same-schema mismatch through Artisan without overwriting', function (string $mismatch): void {
    $row = legacySavedInputRow();
    app()->maintenanceMode()->activate([]);
    app(LegacySavedInputsMigration::class)->run(true);
    $particle = BeamParticle::findOrFail($row['id']);
    if ($mismatch === 'owner') {
        $particle->meta = ['owner' => ActorOwnedParticleAccess::owner(User::factory()->create())];
    } elseif ($mismatch === 'payload') {
        $particle->payload = [...$particle->payload, 'name' => 'Changed'];
    } else {
        $particle->setAttribute($mismatch, '2024-01-01 00:00:00');
    }
    $particle->timestamps = false;
    $particle->save();
    $before = $particle->fresh()->getRawOriginal();
    $this->artisan('calcucrypt:migrate-saved-inputs --apply')->assertExitCode(1);
    expect($particle->fresh()->getRawOriginal())->toEqual($before);
})->with(['owner', 'payload', 'created_at', 'updated_at']);

it('identifies the source UUID for legacy row-shape failures', function (array $invalid): void {
    $row = legacySavedInputRow($invalid);
    $before = BeamParticle::query()->orderBy('id')->get()->map->getRawOriginal()->all();
    try {
        app(LegacySavedInputsMigration::class)->run(false);
        $this->fail('Malformed row was accepted');
    } catch (ValidationException $exception) {
        expect(array_keys($exception->errors()))->toBe(['legacy.'.$row['id']]);
    }
    expect(BeamParticle::query()->orderBy('id')->get()->map->getRawOriginal()->all())->toEqual($before);
})->with([[['name' => str_repeat('x', 81)]], [['created_at' => null]], [['updated_at' => null]]]);
