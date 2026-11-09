<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Splicewire\Beam\Facades\Beam;

/**
 * A team's plan: `free`, `trader` or `pro`. A plan belongs to the team, not the user, so every member
 * of a paid team gets its calculators. The plan name is a bundle in `config/beam/accounts.php`
 * (`entitlements.bundles`); `free` has no bundle and so unlocks nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table(Beam::table('teams'), function (Blueprint $table): void {
            $table->string('plan')->default('free')->after('personal_team');
        });
    }

    public function down(): void
    {
        Schema::table(Beam::table('teams'), function (Blueprint $table): void {
            $table->dropColumn('plan');
        });
    }
};
