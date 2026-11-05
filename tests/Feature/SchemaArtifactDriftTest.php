<?php

declare(strict_types=1);

use Rushing\Doctor\DoctorStatus;
use Splicewire\Beam\Surgeon\SchemaProjectionDriftAudit;

uses(Tests\TestCase::class);

/**
 * `resources/schemas/App/**` is a COMMITTED generated artifact — beam's particle doctrine says so
 * ("schemas:generate → resources/schemas (JSON Schema, committed)"), and `.prettierignore` already
 * carries it as "PHP Data generator output". Until now the tracking was simply never done, so a fresh
 * clone had no projections at all and nothing said a word.
 *
 * This is the verification half of docs/conventions/regenerating-committed-artifacts.md — *regeneration
 * is a command, verification is a test, the test never writes*. It writes nothing; `schemas:generate`
 * is the command, and the audit already names it in its own failure text.
 *
 * ⚠️ It drives {@see SchemaProjectionDriftAudit} rather than re-deriving the expected schema. That is
 * deliberate and the audit's own docblock says why: a second hand-rolled copy of the generation logic
 * DISAGREED with the real command and cost a host a permanent phantom "stale" finding no regeneration
 * could clear. One implementation, driven from a test — never a second one to compare against.
 */
it('has a committed schema projection for every declared Data class', function () {
    $findings = SchemaProjectionDriftAudit::forApp()->run();

    // Drift is reported one finding per offending class, so the clean shape is exactly one summary.
    // Print the details on failure — "expected 1, got 3" alone would not say WHICH class drifted.
    expect($findings)->toHaveCount(
        1,
        'schema projections are missing or stale: '
            .implode(' | ', array_map(fn ($f): string => $f->detail, $findings))
    );

    $finding = $findings[0];

    expect($finding->status)->toBe(DoctorStatus::Pass, $finding->detail);

    // ⚠️ Both of the audit's vacuous outcomes report Pass, and one of them reports it CONCLUSIVELY:
    //   - an empty population  → Finding::inconclusive(), Pass + conclusive:false
    //   - an unavailable check → Finding::pass('... — schema-projection drift check skipped.')
    // So asserting Pass proves nothing on its own. The conclusive flag rejects the first, and pinning
    // the success detail's exact shape rejects the second — a "skipped" pass cannot match this.
    expect($finding->conclusive)->toBeTrue($finding->detail);
    expect($finding->detail)->toMatch('/^\d+ declared Data class\(es\) have fresh disk schema projections\.$/');

    // ...and the population must be non-empty, so a scope regression that silently stops seeing
    // app/Data cannot pass as "clean".
    preg_match('/^(\d+) /', $finding->detail, $m);
    expect((int) $m[1])->toBeGreaterThanOrEqual(1, $finding->detail);
});

/**
 * Regenerate into a throwaway directory and compare it, byte for byte, against the committed tree.
 *
 * The audit above sees only DECLARED particle classes (one, at this starter), so on its own it left
 * every other projection unguarded against the failure that matters most: STALENESS. Change a property on
 * a page-data class, skip `schemas:generate`, and the audit — and the structural existence check this
 * replaced — both stayed green. Comparing a real regeneration closes all four holes at once:
 *
 *   - a class with no committed projection  → present in the regeneration, absent from the tree
 *   - a projection whose class is gone      → present in the tree, absent from the regeneration
 *   - a projection that is stale            → same path, different bytes
 *   - a projection that is empty or corrupt → same path, different bytes
 *
 * Gate ownership: in CI, `composer setup` regenerates resources/schemas before this test runs, so the
 * `stale` and `missing` buckets cannot fail there; the tests.yml clean-tree step owns staleness in CI.
 * This test owns `orphaned` everywhere, and `stale`/`missing` in a local `composer ci:check` run without setup.
 *
 * It drives the REAL command through its first-class `--output` override rather than re-deriving a
 * schema, for the reason the audit's own docblock records: a second hand-rolled copy of the generation
 * logic disagreed with the real one and cost a host a permanent phantom finding. This is the estate's
 * named precedent for the shape — splicewire-app's SdkRegenDriftGuardTest — and it keeps the convention:
 * the test writes only to a throwaway directory, never to the tracked tree.
 *
 * ⚠️ `--output` re-points `filesystems.disks.<schema disk>.root` in config and forgets the resolved disk.
 * SchemaProjectionDriftAudit reads through that SAME disk, so a leaked redirect would make the audit
 * compare the throwaway directory against itself and pass vacuously. The `finally` restores both, rather
 * than trusting per-test application refresh to do it.
 *
 * Environment note: the committed projections are generated with SCHEMA_BASE_URI unset, so their `$id`
 * is the class short name. A machine that sets SCHEMA_BASE_URI will regenerate different bytes and fail
 * here — which is correct: that machine's output is not what is committed.
 */
it('regenerates to exactly the committed schema projections', function () {
    $snapshot = function (string $root): array {
        if (! is_dir($root)) {
            return [];
        }

        $files = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
            if (str_ends_with($file->getPathname(), '.schema.json')) {
                $files[substr($file->getPathname(), strlen($root) + 1)] = (string) file_get_contents($file->getPathname());
            }
        }
        ksort($files);

        return $files;
    };

    $config = config('data-schemas');
    $disk = Schemastud\DataSchemas\Support\SchemaDisk::name($config);
    $originalRoot = config("filesystems.disks.{$disk}.root");
    $tmp = sys_get_temp_dir().'/schema-regen-'.bin2hex(random_bytes(6));

    try {
        $exit = Illuminate\Support\Facades\Artisan::call('schemas:generate', ['--output' => $tmp]);
        expect($exit)->toBe(0, Illuminate\Support\Facades\Artisan::output());

        $generated = $snapshot($tmp.'/App');
        $committed = $snapshot(resource_path('schemas/App'));

        // Guard the instrument: an empty regeneration would make every comparison below vacuous.
        expect($generated)->not->toBeEmpty('regeneration emitted nothing, so the comparison did not run');

        $missing = array_values(array_diff(array_keys($generated), array_keys($committed)));
        $orphaned = array_values(array_diff(array_keys($committed), array_keys($generated)));
        $stale = array_values(array_filter(
            array_keys(array_intersect_key($generated, $committed)),
            fn (string $path): bool => $generated[$path] !== $committed[$path],
        ));

        expect(['missing' => $missing, 'orphaned' => $orphaned, 'stale' => $stale])->toBe(
            ['missing' => [], 'orphaned' => [], 'stale' => []],
            'committed schema projections do not match a fresh regeneration — run `php artisan schemas:generate`',
        );
    } finally {
        config(['data-schemas' => $config, "filesystems.disks.{$disk}.root" => $originalRoot]);
        Illuminate\Support\Facades\Storage::forgetDisk($disk);
        Illuminate\Support\Facades\File::deleteDirectory($tmp);
    }
});
