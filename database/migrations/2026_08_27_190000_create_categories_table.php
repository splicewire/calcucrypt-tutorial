<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The publishing taxonomy a Guide is filed under.
 *
 * This table never existed at this host. Its migration shipped inside
 * `splicewire/laravel-satellite-publishing`, which `adcc428` removed from `composer.json` — so the
 * schema left with the package while three app files kept importing its models. See
 * `app/Models/Category.php` for the full account and why the plane is host-owned now.
 *
 * Shape is recovered from the two things that still describe it: the subclass docblock
 * (`id`/`name`/`slug`/`parent_id`) and `content:sync`, which does
 * `firstOrCreate(['slug' => …], ['name' => …])`. Nothing observed ever wrote `parent_id`, but it is
 * kept because `Category::parent()`/`children()` are declared against it — dropping the column would
 * silently break a relation rather than fail loudly.
 *
 * uuid keys to match the existing `post_calculator` pivot, which types `post_id`/`calculator_id` as
 * `uuid`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->uuid('parent_id')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
