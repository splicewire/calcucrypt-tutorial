<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calculators', function (Blueprint $table): void {
            // The entitlement key that unlocks the calculator (`calculators.{slug}`), or null when it
            // is free. Projected from CalculatorDefinition::entitlement() by content:sync.
            $table->string('entitlement')->nullable()->after('computation');
        });
    }

    public function down(): void
    {
        Schema::table('calculators', function (Blueprint $table): void {
            $table->dropColumn('entitlement');
        });
    }
};
