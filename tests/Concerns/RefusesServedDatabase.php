<?php

namespace Tests\Concerns;

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Tests\Support\ScratchDatabase;

/**
 * A test that rebuilds its database (`migrate:fresh`) first refuses any database that is not a scratch one
 * ({@see ScratchDatabase}): sqlite `:memory:`, or a server database under `beam.dev.prefix`. The parent tiers run on the
 * first, a Postgres tier (tower; a host's isolated suite database) on the second.
 *
 * Such a test writes outside any test transaction. A server database outlives it, so its rows would be read by the next
 * RefreshDatabase test, which migrates once per process and then only wraps each test in a transaction. The test
 * therefore hands that next test a fresh migration. On sqlite `:memory:` the database dies with the connection and
 * RefreshDatabase migrates every test anyway, so nothing changes there.
 */
trait RefusesServedDatabase
{
    /** Call before the test migrates its database. */
    protected function assertScratchDatabase(): void
    {
        $connection = DB::connection();
        $refusal = ScratchDatabase::refusal($connection->getDriverName(), (string) $connection->getDatabaseName(), config('beam.dev.prefix'));

        $this->assertNull($refusal, "Refuse tests against a served DB: {$refusal}.");
        $this->beforeApplicationDestroyed(static function (): void {
            RefreshDatabaseState::$migrated = false;
        });
    }
}
