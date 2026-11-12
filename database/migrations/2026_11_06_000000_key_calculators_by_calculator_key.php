<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Calculators are identified by their Calculator Key (ADR 0005): a unique `key` column replaces the
 * indexed, non-unique `computation` one, and the definition's free Tags get a projected `tags` column.
 * Both are projections of authored source; `content:sync` rewrites every row's key after this runs.
 * The existing `computation` values are already distinct, so the rename satisfies the unique index
 * until the sync replaces them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calculators', function (Blueprint $table): void {
            $table->dropIndex(['computation']);
        });

        Schema::table('calculators', function (Blueprint $table): void {
            $table->renameColumn('computation', 'key');
        });

        Schema::table('calculators', function (Blueprint $table): void {
            $table->unique('key');
            $table->json('tags')->nullable()->after('key');
        });
    }

    public function down(): void
    {
        Schema::table('calculators', function (Blueprint $table): void {
            $table->dropColumn('tags');
            $table->dropUnique(['key']);
        });

        Schema::table('calculators', function (Blueprint $table): void {
            $table->renameColumn('key', 'computation');
        });

        Schema::table('calculators', function (Blueprint $table): void {
            $table->index('computation');
        });
    }
};
