<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\RefusesServedDatabase;
use Tests\Concerns\WithholdsTables;
use Tests\TestCase;

/**
 * The missing-source helper on whatever database the suite uses. It owns one throwaway table, so it needs no migration
 * and no RefreshDatabase. It is the proof that the table comes back, with its rows, on the savepoint path and on the
 * explicit-restore path a driver with implicitly committing DDL (MySQL) takes.
 */
class WithholdsTablesTest extends TestCase
{
    use RefusesServedDatabase;
    use WithholdsTables;

    private const TABLE = 'b33_withheld_probe';

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertScratchDatabase();
        $this->dropProbeTables();
        Schema::create(self::TABLE, function (Blueprint $table): void {
            $table->id();
            $table->string('label');
        });
        DB::table(self::TABLE)->insert([['label' => 'first'], ['label' => 'second']]);
    }

    protected function tearDown(): void
    {
        $this->dropProbeTables();
        parent::tearDown();
    }

    public function test_the_table_is_missing_inside_and_back_with_its_rows_after_on_this_driver(): void
    {
        $this->withoutTable(self::TABLE, fn () => $this->assertMissing());
        $this->assertRows();
    }

    public function test_the_explicit_restore_path_brings_the_table_back_without_a_savepoint(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            $this->markTestSkipped('Postgres has transactional DDL; a failed read there aborts any open transaction, so it never takes this path.');
        }

        $this->withoutTable(self::TABLE, fn () => $this->assertMissing(), transactionalDdl: false);
        $this->assertRows();
    }

    public function test_a_failing_probe_still_restores_the_table(): void
    {
        try {
            $this->withoutTable(self::TABLE, function (): void {
                throw new \RuntimeException('probe failed');
            });
            $this->fail('The probe failure was swallowed.');
        } catch (\RuntimeException $error) {
            $this->assertSame('probe failed', $error->getMessage());
        }
        $this->assertTrue(Schema::hasTable(self::TABLE));
        $this->assertRows();
    }

    public function test_an_enclosing_transaction_stays_usable_where_ddl_is_transactional(): void
    {
        if (! static::hasTransactionalDdl(DB::connection()->getDriverName())) {
            $this->markTestSkipped('A rename commits this driver\'s transaction implicitly; there is no enclosing transaction to keep.');
        }

        DB::beginTransaction();
        try {
            $this->withoutTable(self::TABLE, fn () => $this->assertMissing());
            $this->assertRows();
        } finally {
            DB::rollBack();
        }
    }

    private function assertMissing(): void
    {
        try {
            DB::table(self::TABLE)->count();
            $this->fail('The withheld table was still readable.');
        } catch (QueryException) {
            // The read the probe exists to provoke; on Postgres it aborts the savepoint the helper opened.
        }
    }

    private function assertRows(): void
    {
        $this->assertSame(['first', 'second'], DB::table(self::TABLE)->orderBy('id')->pluck('label')->all());
    }

    private function dropProbeTables(): void
    {
        Schema::dropIfExists(self::TABLE);
        Schema::dropIfExists(self::TABLE.'_unavailable');
    }
}
