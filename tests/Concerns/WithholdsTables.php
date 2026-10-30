<?php

namespace Tests\Concerns;

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;

/**
 * A test that proves a missing source fails closed renames that source's table away, probes, and must put the table
 * back on whatever driver the host's suite uses.
 *
 * - Where DDL is transactional (pgsql, sqlite, sqlsrv), the rename and the probe sit in a savepoint. Postgres aborts the
 *   enclosing transaction at the first read of the missing table, and only a rollback to the savepoint clears that. The
 *   rollback also undoes the rename.
 * - Where DDL commits implicitly (mysql, mariadb: `RENAME TABLE` ends the transaction), there is no savepoint to return
 *   to. The table is renamed back explicitly. That commit also ended any RefreshDatabase transaction, so the next
 *   RefreshDatabase test is handed a fresh migration.
 *
 * Either way, the `finally` renames the table back if it is still absent, so the next assertion and the next test meet it.
 */
trait WithholdsTables
{
    /** Run `$probe` while `$table` is absent, then restore it. `$transactionalDdl` overrides the driver's answer (tests of this helper). */
    protected function withoutTable(string $table, callable $probe, ?bool $transactionalDdl = null): void
    {
        $connection = DB::connection();
        $transactionalDdl ??= static::hasTransactionalDdl($connection->getDriverName());
        $aside = $table.'_unavailable';

        if ($transactionalDdl) {
            $connection->beginTransaction();
        }

        try {
            $connection->getSchemaBuilder()->rename($table, $aside);
            $probe();
        } finally {
            if ($transactionalDdl) {
                $connection->rollBack();
            } else {
                RefreshDatabaseState::$migrated = false;
            }

            $schema = $connection->getSchemaBuilder();
            if (! $schema->hasTable($table) && $schema->hasTable($aside)) {
                $schema->rename($aside, $table);
            }
        }

        $this->assertTrue($connection->getSchemaBuilder()->hasTable($table), "[{$table}] was not restored after it was withheld.");
    }

    /** Whether a rename on this driver can be rolled back with the transaction it ran in. */
    public static function hasTransactionalDdl(string $driver): bool
    {
        return in_array($driver, ['pgsql', 'sqlite', 'sqlsrv'], true);
    }
}
