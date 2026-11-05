<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Rushing\SchemaConvergence\ConvergentTable;
use Splicewire\Beam\Taxonomy\BeamTaxonomyServiceProvider;

/**
 * Beam taxonomy base tables — the hierarchical `BeamSilo` facet + its `siloable` morph pivot. SHARED
 * (central + every tenant — "everything is shared by default"): published to the SINGLE
 * `database/migrations/shared/` destination via `->hasMigrations([...])` in
 * {@see BeamTaxonomyServiceProvider::configurePackage()} — one file, not a duplicated flat+tenant pair
 * — so `silos`/`siloables` exist identically in central and every tenant schema. beam-tenancy's
 * `registerSharedMigrationsPath()` runs `database/migrations/shared/` in both the central `migrate`
 * pass and Stancl's tenant pass. Both creates carry the CONVERGENT guard
 * (`docs/agents/convergent-migration-guards.convention.md` in rushing/laravel-schema-convergence), which covers the host that
 * migrates both passes into one schema (the shared-test-DB harness) — and, unlike the bare `hasTable`
 * return it replaced, it guards `siloables` too.
 *
 * Beam ships only the BASE shape. The federation-scope columns (`silos.is_grantable`,
 * `silos.default_member_scope`, `siloables.scope`) are a satellite/federation concern and stay in
 * tower as a tenant-only ALTER (`add_federation_scope_to_silos`) — free beam never carries a
 * federation column. `silos.name_path` is a generic hierarchy/materialized-path column (silos already
 * carry `parent_id`), not a tower concern, so it's folded directly into this base create rather than
 * staying as tower's `add_silo_name_path` tenant ALTER — `silos` is pre-prod, so there's no deployed
 * data whose migration history needs preserving.
 *
 * PUBLISH-ONLY stub (the estate-wide convention): this file carries no timestamp — `vendor:publish
 * --tag=beam-taxonomy-migrations` re-stamps it to the install moment via spatie/laravel-package-tools'
 * `generateMigrationName`, sequenced ahead of the tenant-only `add_external_ref_to_taxonomy_tables`
 * ALTER that targets these tables (declared after it in `->hasMigrations([...])`).
 */
return new class extends Migration
{
    public function up(): void
    {
        ConvergentTable::named('silos')
            ->define(function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('slug');
                $table->uuid('parent_id')->nullable()->index();
                $table->string('name_path', 1024)->nullable();
                $table->timestamps();
            })
            ->assert();

        ConvergentTable::named('siloables')
            ->define(function (Blueprint $table) {
                $table->foreignUuid('silo_id')->constrained()->cascadeOnDelete();
                $table->uuid('siloable_id');
                $table->string('siloable_type');
                $table->unique(['silo_id', 'siloable_id', 'siloable_type']);
            })
            ->assert();
    }

    public function down(): void
    {
        Schema::dropIfExists('siloables');
        Schema::dropIfExists('silos');
    }
};
