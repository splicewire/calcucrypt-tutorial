<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Splicewire\Beam\Taxonomy\Resolution\AuthorityAwareResolver;

/**
 * sourced-particles ticket 05 — provenance on the taxonomy tables so a foreign-only shadow
 * tag/silo carries its source authority + a source-scoped `external_ref`, and the authority-aware
 * resolver ({@see AuthorityAwareResolver}) can dedup a
 * re-materialize idempotently.
 *
 * `external_ref` is the composed `{authority}::{naturalKey}` the resolver keys STRICTLY on;
 * `source_authority` records the declaring authority alone. A local tag/silo leaves both NULL.
 *
 * Tenant-scoped (each tenant its own schema) — mirrors the fragments `external_ref` migration.
 * The unique index is PARTIAL (`WHERE external_ref IS NOT NULL`) so unconstrained local rows carry
 * a NULL ref while every foreign-mint is held unique per authority+key — which is what makes the
 * resolver's get-or-create race-safe and closes the silo scope-leak by identity, not by name_path.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['tags', 'silos'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('external_ref')->nullable()->after('slug');
                $t->string('source_authority')->nullable()->after('external_ref');
            });

            DB::statement("CREATE UNIQUE INDEX {$table}_external_ref_unique ON {$table} (external_ref) WHERE external_ref IS NOT NULL");
        }
    }

    public function down(): void
    {
        foreach (['tags', 'silos'] as $table) {
            DB::statement("DROP INDEX IF EXISTS {$table}_external_ref_unique");

            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn(['external_ref', 'source_authority']);
            });
        }
    }
};
