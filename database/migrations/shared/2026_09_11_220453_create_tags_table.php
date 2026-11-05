<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Rushing\SchemaConvergence\ConvergentTable;
use Splicewire\Beam\Taxonomy\BeamTaxonomyServiceProvider;

/**
 * Beam taxonomy base tables — `tags` + its `taggables` morph pivot. SHARED (central + every tenant —
 * "everything is shared by default"): published to the SINGLE `database/migrations/shared/`
 * destination via `->hasMigrations([...])` in {@see BeamTaxonomyServiceProvider::configurePackage()} —
 * one file, not a duplicated flat+tenant pair — so `tags`/`taggables` exist identically in central and
 * every tenant schema. beam-tenancy's `registerSharedMigrationsPath()` runs
 * `database/migrations/shared/` in both the central `migrate` pass and Stancl's tenant pass. Both
 * creates carry the CONVERGENT guard (`docs/agents/convergent-migration-guards.convention.md` in
 * rushing/laravel-schema-convergence), which covers the host that migrates both passes into one schema (the shared-test-DB
 * harness) — and, unlike the bare `hasTable` return it replaced, it guards `taggables` too.
 *
 * PUBLISH-ONLY stub (the estate-wide convention): this file carries no timestamp — `vendor:publish
 * --tag=beam-taxonomy-migrations` re-stamps it to the install moment, sequenced ahead of the
 * tenant-only `add_external_ref_to_taxonomy_tables` ALTER that targets this table (declared after it
 * in `->hasMigrations([...])`).
 */
return new class extends Migration
{
    public function up(): void
    {
        ConvergentTable::named('tags')
            ->define(function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name', 512);
                $table->string('slug', 512);
                $table->string('type')->nullable();
                $table->timestamps();
            })
            ->assert();

        ConvergentTable::named('taggables')
            ->define(function (Blueprint $table) {
                $table->foreignUuid('tag_id')->constrained()->cascadeOnDelete();
                $table->uuid('taggable_id');
                $table->string('taggable_type');
                $table->unique(['tag_id', 'taggable_id', 'taggable_type']);
            })
            ->assert();
    }

    public function down(): void
    {
        Schema::dropIfExists('taggables');
        Schema::dropIfExists('tags');
    }
};
