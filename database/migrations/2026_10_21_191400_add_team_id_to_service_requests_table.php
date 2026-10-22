<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_requests', function (Blueprint $table): void {
            // Historical private rows stay unassigned; a present team cannot establish past ownership.
            $table->foreignId('team_id')->nullable()->constrained('beam_teams')->restrictOnDelete();
            $table->index('team_id');
        });
    }

    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table): void {
            $table->dropIndex(['team_id']);
            $table->dropConstrainedForeignId('team_id');
        });
    }
};
