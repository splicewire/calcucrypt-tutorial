<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Splicewire\Beam\Facades\Beam;

/**
 * The invitations table. Formerly `invitations`, renamed `invitations` → `beam_invitations`
 * (beam-particle-rename ticket 04), routed through the single table-prefix seam {@see Beam::table()}.
 *
 * Data-preserving rename (NOT drop+create): where the pre-rename `invitations` already exists in THIS
 * schema, it is renamed in place, preserving every row and its FK to `beam_teams` (Postgres keeps the
 * reference valid across a parent rename). A fresh install (neither table present) creates the target
 * directly with the FK pointed at the PREFIXED parent (`beam_teams`) — the create-branch footgun this
 * slice fixes. Guarded on the CURRENT schema explicitly (the CLAUDE.md footgun) so the logic is
 * order-independent and idempotent.
 *
 * NOT squashed with `add_lifecycle_to_invitations_table` — this whole `teams/` directory converts 1:1
 * (extension/location/registration only, logic untouched): the data-preserving rename above already
 * ran against real deployed data, so folding the later lifecycle-columns ALTER into a "clean create"
 * would silently skip landing those columns on a host that already ran the original migrations.
 */
return new class extends Migration
{
    public function up(): void
    {
        $currentSchema = DB::getDriverName() === 'pgsql'
            ? DB::selectOne('select current_schema() as schema')->schema
            : null;

        if ($this->existsInCurrentSchema($currentSchema, $this->target())) {
            return; // Already renamed / created in this schema — idempotent re-run.
        }

        // Data-preserving rename of the pre-rename table when it is present in THIS schema.
        if ($this->existsInCurrentSchema($currentSchema, 'invitations')) {
            Schema::rename('invitations', $this->target());

            return;
        }

        // Fresh install: neither name present in this schema — create the target directly, with the
        // FK pointed at the PREFIXED parent table.
        Schema::create($this->target(), function (Blueprint $table): void {
            $table->id();
            // The OWNING TEAM, as a `TeamContract` key rather than as a row in one table.
            //
            // This was `foreignId('team_id')->constrained(Beam::table('teams'))->cascadeOnDelete()`
            // until 2026-08-31. That one line made both promises a `TeamContract` refuses to make — a
            // WIDTH (bigint) and a TABLE (`beam_teams`) — while the contract's signature is
            // `teamKey(): int|string` and its docblock says it "says nothing about the backing table,
            // key type, or provisioning: those are the host's private seam". A host whose team notion
            // is a `Tenant` keys on a STRING, so the FK made the OTB invitation structurally unable to
            // own one. Converged with the shipped stub; see beam-accounts.
            $table->string('team_id')->index();
            $table->string('email');
            $table->string('role')->default('member');
            $table->string('token')->unique();
            $table->timestamps();

            $table->unique(['team_id', 'email']);
        });
    }

    public function down(): void
    {
        $currentSchema = DB::getDriverName() === 'pgsql'
            ? DB::selectOne('select current_schema() as schema')->schema
            : null;

        // Reverse the rename when the target is the one present in this schema.
        if ($this->existsInCurrentSchema($currentSchema, $this->target())) {
            Schema::rename($this->target(), 'invitations');
        }
    }

    private function target(): string
    {
        return Beam::table('invitations');
    }

    /**
     * Outside Postgres there is no per-connection "current schema"/search_path concern (the
     * CLAUDE.md footgun this guard exists for in the first place) — sqlite/mysql only ever see one
     * namespace per connection, so a plain {@see Schema::hasTable()} is exact and driver-safe there.
     */
    private function existsInCurrentSchema(?string $schema, string $table): bool
    {
        if ($schema === null) {
            return Schema::hasTable($table);
        }

        return DB::selectOne(
            'select 1 from information_schema.tables where table_schema = ? and table_name = ?',
            [$schema, $table],
        ) !== null;
    }
};
