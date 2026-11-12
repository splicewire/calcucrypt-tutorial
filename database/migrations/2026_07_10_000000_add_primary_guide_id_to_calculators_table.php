<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calculators', function (Blueprint $table): void {
            // The one canonical Guide that explains this calculator. Nullable and
            // unconstrained: content:sync resolves it after the published Guide index is refreshed, so a
            // definition may name a Guide authored later.
            $table->uuid('primary_guide_id')->nullable()->index()->after('category_id');
        });
    }

    public function down(): void
    {
        Schema::table('calculators', function (Blueprint $table): void {
            $table->dropIndex(['primary_guide_id']);
            $table->dropColumn('primary_guide_id');
        });
    }
};
