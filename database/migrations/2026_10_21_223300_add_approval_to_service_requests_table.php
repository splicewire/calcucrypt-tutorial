<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_requests', function (Blueprint $table): void {
            // Existing rows have no approval decision; status, creator and quarantine stay intact.
            $table->string('approval_state')->default('pending');
            $table->uuid('workflow_version')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table): void {
            $table->dropColumn(['approval_state', 'workflow_version']);
        });
    }
};
