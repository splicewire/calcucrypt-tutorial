<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;
use Tests\Support\ScratchDatabase;

/**
 * A test that rebuilds its database (`migrate:fresh`) first refuses any database that is not a scratch one
 * ({@see ScratchDatabase}): sqlite `:memory:`, or a server database under `beam.dev.prefix`. The parent tiers run on the
 * first, a Postgres tier (tower; a host's isolated suite database) on the second.
 */
trait RefusesServedDatabase
{
    /** Call before the test migrates its database. */
    protected function assertScratchDatabase(): void
    {
        $connection = DB::connection();
        $refusal = ScratchDatabase::refusal($connection->getDriverName(), (string) $connection->getDatabaseName(), config('beam.dev.prefix'));

        $this->assertNull($refusal, "Refuse tests against a served DB: {$refusal}.");
    }
}
