<?php

declare(strict_types=1);

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
it('has no particle-declared Data in the application disk schema scope before chapter one', function (): void {
    // This is the audit's population: particle resource data/input and operation input/output,
    // restricted to the configured application schema paths. ResponseFromData page contracts
    // and supplied Data still exist; the unconditional real-regeneration test below checks them.
    $paths = (array) config('data-schemas.auto_discover_types', [app_path('Data')]);
    $classes = [];
    $resources = app(Splicewire\Beam\Particle\ParticleResourceRegistry::class);
    foreach ($resources->keys() as $key) {
        $resource = $resources->resolve($key); // unfiltered declaration identity, not guest reach
        foreach ([$resource->data, $resource->input] as $class) {
            if (is_string($class)) {
                $classes[] = $class;
            }
        }
    }
    $operations = app(Splicewire\Beam\Particle\ParticleOperationRegistry::class);
    foreach ($operations->keys() as $key) {
        $operation = $operations->resolve($key);
        foreach ([$operation->input, $operation->output] as $slot) {
            $slotValues = is_array($slot) ? $slot : [$slot];
            array_walk_recursive($slotValues, function ($class) use (&$classes): void {
                if (is_string($class)) {
                    $classes[] = $class;
                }
            });
        }
    }
    $inScope = array_values(array_filter(array_unique($classes), function (string $class) use ($paths): bool {
        if (! class_exists($class) || ! is_subclass_of($class, Spatie\LaravelData\Data::class)) {
            return false;
        }
        $file = (new ReflectionClass($class))->getFileName();

        return $file !== false && array_any($paths, fn ($path): bool => str_starts_with($file, rtrim((string) $path, '/').'/'));
    }));
    expect($inScope)->toBe([]);

    // Independent physical declaration probe. No product resource/op attribute is shipped yet;
    // it does not pretend that other App Data or page wire declarations are absent.
    $attributed = [];
    foreach ([app_path('Data'), app_path('Particle')] as $path) {
        if (! is_dir($path)) {
            continue;
        }
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)) as $file) {
            if (! str_ends_with($file->getPathname(), '.php')) {
                continue;
            }
            $tokens = token_get_all((string) file_get_contents($file->getPathname()));
            foreach ($tokens as $index => $token) {
                if (! is_array($token) || $token[0] !== T_ATTRIBUTE) {
                    continue;
                }
                for ($next = $index + 1; $next < count($tokens); $next++) {
                    $name = $tokens[$next];
                    if ($name === ']') {
                        break;
                    }
                    if (is_array($name) && in_array($name[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                        $leaf = basename(str_replace('\\', '/', $name[1]));
                        if (in_array($leaf, ['ParticleResource', 'ParticleOp'], true)) {
                            $attributed[] = $file->getPathname();
                            break;
                        }
                    }
                }
            }
        }
    }
    expect($attributed)->toBe([]);
    $findings = SchemaProjectionDriftAudit::forApp()->run();
    expect($findings)->toHaveCount(1)
        ->and($findings[0]->conclusive)->toBeFalse();
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
