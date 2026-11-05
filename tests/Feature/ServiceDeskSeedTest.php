<?php

use App\Workflows\ServiceRequestApproval;
use Splicewire\Beam\Seed\BeamSeedManifest;
use Splicewire\Beam\Workflows\Definition\DefinitionStore;
use Tests\Concerns\RefusesServedDatabase;
use Tests\TestCase;

uses(TestCase::class, RefusesServedDatabase::class);

beforeEach(function () {
    $this->assertScratchDatabase();
    $this->artisan('migrate:fresh')->assertExitCode(0);

    // Exercise the host's registered step through the public seed command, without seeding
    // unrelated packages (their own seed suites cover those steps).
    $step = collect(app(BeamSeedManifest::class)->steps())->firstWhere('package', 'app/service-desk');
    expect($step)->not->toBeNull();
    $manifest = new BeamSeedManifest;
    $manifest->register($step->package, $step->seeder, order: $step->order, configGate: $step->configGate);
    app()->instance(BeamSeedManifest::class, $manifest);
});

it('seeds the approval lineage through the default nonproduction setup step', function () {
    expect(config('examples.service_desk.seed'))->toBeTrue();
    $this->artisan('splicewire:beam:seed')
        ->expectsOutputToContain('1 seeded, 0 skipped (gate off), 0 FAILED.')
        ->assertExitCode(0);
    expect(app(DefinitionStore::class)->lineageByKey(ServiceRequestApproval::LINEAGE))->not->toBeNull();
});

it('reports the example skipped when its host gate is off', function () {
    config(['examples.service_desk.seed' => false]);
    $this->artisan('splicewire:beam:seed')
        ->expectsOutputToContain('app/service-desk: skipped (examples.service_desk.seed is off)')
        ->expectsOutputToContain('0 seeded, 1 skipped (gate off), 0 FAILED.')
        ->assertExitCode(0);
    expect(app(DefinitionStore::class)->lineageByKey(ServiceRequestApproval::LINEAGE))->toBeNull();
});
