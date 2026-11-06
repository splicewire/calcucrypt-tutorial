<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A Guide — the Post half of the editorial plane. Same provenance as `categories`: the migration
 * shipped inside the retired `splicewire/laravel-satellite-publishing`, so the table left with the
 * package. See `app/Models/Post.php`.
 *
 * Every column here is recovered from an observed writer or reader, not guessed:
 *
 *  - `slug` — `firstOrNew(['slug' => …])` in `content:sync`, and the model's route key
 *  - `title`, `teaser`, `category_id`, `status` — the `fill()` block in `content:sync`
 *  - `body_blocks` (json), `body_blocks_html` — same `fill()`; sync writes `['source' => 'mdx']` as
 *    provenance and the pre-rendered HTML respectively. `Post::displayBody` reads the HTML column,
 *    and `GuideData` is its only consumer.
 *  - `published_at` — set explicitly by `content:sync`, and read by the guide queries
 *
 * ⚠️ **Deliberately no foreign keys**, matching the two migrations that already reference `posts`.
 * `2026_07_10_000000_add_primary_post_id_to_calculators_table` says so in its own comment —
 * *"unconstrained: content:sync resolves it after all Posts are upserted"* — and
 * `create_post_calculator_table` likewise types `post_id`/`calculator_id` as bare indexed uuids. A
 * constrained FK here would break that upsert ordering, so the absence is the existing contract
 * rather than an oversight.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('teaser')->nullable();
            $table->uuid('category_id')->nullable()->index();
            $table->string('status')->default('draft')->index();
            $table->json('body_blocks')->nullable();
            $table->longText('body_blocks_html')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
