<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Association (CONTEXT.md) — the many-to-many between a Guide index and a Calculator, in
 * either of two modes: `embed` (interactive inline) or `link` (a CTA to the tool page). The
 * `anchor` targets a spot in the guide body; `position` orders multiple associations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calculator_guide', function (Blueprint $table): void {
            $table->uuid('guide_id')->index();
            $table->uuid('calculator_id')->index();
            $table->string('mode')->default('embed');
            $table->unsignedInteger('position')->default(0);
            $table->string('anchor')->nullable();
            $table->timestamps();

            $table->unique(['guide_id', 'calculator_id', 'mode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calculator_guide');
    }
};
