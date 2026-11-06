<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Calculators — the Crypt's tools, each a graph of Steps (a DAG). The whole graph lives in
 * one `graph` JSON column for now; it is promotable to a `calculator_steps` table with the
 * SAME node schema only when a second computation needs shared fragments (a storage change,
 * not an evaluator rewrite). A calculator is distinguished by its `computation`, never by
 * asset (asset is an input) — so one Profit calculator serves BTC/ETH/SOL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calculators', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('summary')->nullable();
            // The distinguisher (CONTEXT.md): profit, staking-apy, dca, converter, …
            $table->string('computation')->index();
            $table->uuid('category_id')->nullable()->index();
            // The whole DAG: nodes[], outputs[], optional chart hint.
            $table->json('graph');
            $table->string('status')->default('draft')->index();
            // Publish window mirrors Post so a calculator and a guide gate identically.
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('expired_at')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calculators');
    }
};
