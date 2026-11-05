<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The TEAMS estate's `users` ALTER, in shared/ because beam-accounts publishes it there: shared/ runs in the central
 * pass AND every tenant pass (beam-tenancy's registerSharedMigrationsPath()).
 *
 * This starter owns `users` CENTRAL-only (its own 0001_01_01_000000_create_users_table, with beam-accounts'
 * `publish_auth_migrations` 'absent', which is the package's shared `users` create left out). So a tenant schema has
 * no `users`, and the unguarded ALTER failed every tenant pass with SQLSTATE 42P01, relation "users" does not
 * exist: first a fresh Commerce `tenants:migrate`, then a Beam starter --with-market tenant pass (launch G6, 2026-10-17). Ported from
 * laravel-beam-commerce-starter 7c271c9.
 *
 * It now lands `current_team_id` wherever `users` lives and nowhere else. The existence check reads the CURRENT
 * schema explicitly, as create_teams_table does, so the answer cannot depend on a tenant's search_path reaching a
 * central `public.users`. Same file name as before, so a host that already recorded this
 * migration does not run it again; `hasColumn` keeps a re-run idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! $this->usersInCurrentSchema() || Schema::hasColumn('users', 'current_team_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedBigInteger('current_team_id')->nullable()->after('id');
        });
    }

    public function down(): void
    {
        if (! $this->usersInCurrentSchema() || ! Schema::hasColumn('users', 'current_team_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('current_team_id');
        });
    }

    private function usersInCurrentSchema(): bool
    {
        if (DB::getDriverName() !== 'pgsql') {
            return Schema::hasTable('users');
        }

        return DB::selectOne(
            'select 1 from information_schema.tables where table_schema = current_schema() and table_name = ?',
            ['users'],
        ) !== null;
    }
};
