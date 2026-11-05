<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

uses(Tests\TestCase::class);

it('analyses migration stubs before they are published as PHP files', function () {
    $directory = sys_get_temp_dir().'/starter-stub-analysis-'.bin2hex(random_bytes(8));
    $probe = $directory.'/probe';
    File::makeDirectory($probe, recursive: true);
    File::makeDirectory($directory.'/tmp');
    $stub = $probe.'/migration.php.stub';
    $source = <<<'PHP'
<?php

class MigrationStubProbe
{
    public function owner(object $row): mixed
    {
        return $row->user_id;
    }
}
PHP;

    // A PHPStan tmpDir of this run's own. PHPStan caches a file's PHPDoc map by absolute path and
    // revalidates it only by that file's content hash, so a cache shared with another run (the
    // machine-wide default, or `php:types` in the same CI job) can replay a map built wrongly
    // elsewhere, and this run could write one for them.
    $phpstan = function (array $arguments) use ($directory): Process {
        $process = new Process(
            [PHP_BINARY, '-d', 'memory_limit=1G', base_path('vendor/bin/phpstan'), ...$arguments],
            base_path(),
            ['XDEBUG_MODE' => 'off', 'PAO_DISABLE' => '1', 'TMPDIR' => $directory.'/tmp'],
        );
        $process->setTimeout(60);
        $process->run();

        return $process;
    };

    $analyse = fn (?string $path = null): Process => $phpstan([
        'analyse', '--configuration='.base_path('phpstan.migration-stubs.neon'),
        '--error-format=json', '--no-progress',
        ...($path === null ? ['--debug'] : [$path]),
    ]);

    $configuredPaths = function (string $configuration) use ($phpstan): array {
        $dump = $phpstan(['dump-parameters', '--json', '--configuration='.base_path($configuration)]);
        expect($dump->getExitCode())->toBe(0, $dump->getOutput().$dump->getErrorOutput());

        return json_decode($dump->getOutput(), true, flags: JSON_THROW_ON_ERROR)['analysedPathsFromConfig'];
    };

    try {
        File::put($stub, $source);
        $broken = $analyse($probe);
        expect($broken->getExitCode())->toBe(1)
            ->and($broken->getOutput())->toContain('property.notFound', 'migration.php.stub');

        File::put($stub, str_replace('$row->user_id;', '$row->user_id ?? null;', $source));
        $fixed = $analyse($probe);
        expect($fixed->getExitCode())->toBe(0, $fixed->getOutput().$fixed->getErrorOutput());
        $report = json_decode($fixed->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        expect($report['totals']['file_errors'])->toBe(0);

        // No CLI path override: removing package sources from the stub configuration must fail too,
        // and so must letting a published copy under database/ into the same process.
        $configured = $analyse();
        expect($configured->getExitCode())->toBe(0, $configured->getOutput().$configured->getErrorOutput())
            ->and($configured->getOutput())->toContain(
                'laravel-beam-accounts/database/migrations/shared/add_slug_to_teams_table.php.stub',
                'laravel-beam-accounts/database/migrations/shared/create_permission_tables.php.stub',
            )
            ->and($configured->getOutput())->not->toContain(base_path('database').DIRECTORY_SEPARATOR);

        // The application gate must never analyse package sources: a stub and its byte-identical
        // published copy in one PHPStan process share an AST and misreport each other's PHPDocs.
        expect($configuredPaths('phpstan.neon'))->each->not->toStartWith(base_path('vendor'))
            ->and($configuredPaths('phpstan.migration-stubs.neon'))->each->toStartWith(base_path('vendor'));
    } finally {
        File::deleteDirectory($directory);
    }
});
