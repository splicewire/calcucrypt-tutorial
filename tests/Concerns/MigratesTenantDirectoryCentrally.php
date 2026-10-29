<?php

namespace Tests\Concerns;

use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

/**
 * The service-desk workflow tables are host migrations under `database/migrations/tenant/`. On a tier without
 * beam-tenancy, laravel-beam-accounts' central fallback (`WiresTeamsMigrations`) adds that directory to the central
 * migrator, so `migrate` and `migrate:fresh` build them. Where beam-tenancy owns `tenant/` (a child tier that requires
 * it), or a host disables `beam.accounts.central_migration_fallback`, the central pass never reaches them.
 *
 * A test that exercises the service desk against the central test database then migrates that same directory itself:
 * the native published migrations, never a fabricated schema. On a tier whose central migrator already holds the
 * directory it migrates nothing, so the parent tier's schema is unchanged.
 */
trait MigratesTenantDirectoryCentrally
{
    /** Call after the test database is migrated (after `migrate:fresh`, or after `parent::setUp()` with RefreshDatabase). */
    protected function migrateTenantDirectoryUnlessCentral(): void
    {
        $tenant = database_path('migrations/tenant');

        if (! in_array($this->normalisedPath($tenant), array_map($this->normalisedPath(...), $this->app->make(Migrator::class)->paths()), true)) {
            $exit = Artisan::call('migrate', ['--path' => $tenant, '--realpath' => true, '--force' => true]);
            $this->assertSame(0, $exit, Artisan::output());
        }

        $this->assertTrue(Schema::hasTable('workflow_definition_lineages'), 'The service-desk workflow tables are missing from the central test database.');
    }

    private function normalisedPath(string $path): string
    {
        return rtrim(realpath($path) ?: $path, '/');
    }
}
