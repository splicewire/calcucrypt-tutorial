<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Live Facts — time-stamped market observations pulled from Connectors (CONTEXT.md
 * "Reference / Live Fact"). Deliberately a short-TTL, high-churn table: volatile prices
 * stay here and never pollute the durable knowledge engine. `key` = connector:metric:symbol.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facts', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->index();
            $table->string('connector');
            $table->string('metric');
            $table->string('symbol');
            $table->decimal('value', 36, 18);
            $table->string('source');
            // The observation moment as reported by the source payload — NOT created_at.
            $table->timestamp('as_of');
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->index(['key', 'as_of']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facts');
    }
};
