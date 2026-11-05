<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use Splicewire\Beam\Accounts\BeamAccountsServiceProvider;
use Splicewire\Beam\Install\MigrationFiles;

uses(Tests\TestCase::class);

it('resolves account migration publication onto the existing host migration', function () {
    $files = MigrationFiles::in(MigrationFiles::pathsFor(app()));
    $checked = [];

    foreach (ServiceProvider::pathsToPublish(BeamAccountsServiceProvider::class) as $source => $destination) {
        if (! str_ends_with($source, '.php.stub')) {
            continue;
        }

        $stem = basename($source, '.php.stub');
        $existing = array_values(array_filter($files, fn (array $file): bool => $file[1] === $stem));
        if ($existing === []) {
            continue; // A new package migration legitimately has no host copy yet.
        }

        expect($existing)->toHaveCount(1, $stem.' has multiple runnable copies');
        expect(realpath($destination))->toBe(realpath($existing[0][2]), $stem.' would publish beside its host copy');
        $checked[] = $stem;
    }

    // Positive controls: an empty publish map or an unseen shared directory must not pass.
    expect($checked)->toContain('create_teams_table', 'add_slug_to_teams_table');
});
