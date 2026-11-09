<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A reader's named set of inputs for one calculator ("my usual ETH position"). Owned by the user who saved it;
 * it goes when either the user or the calculator does.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_inputs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('calculator_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('inputs');
            $table->timestamps();

            $table->index(['user_id', 'calculator_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_inputs');
    }
};
