<?php

declare(strict_types=1);

use App\Models\Calculator;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
});

it('reproduces the calculators and the profit guide from authored source', function (): void {
    Artisan::call('content:sync');

    expect(Calculator::where('slug', 'crypto-profit')->exists())->toBeTrue()
        ->and(Calculator::published()->count())->toBe(4);

    $guide = Post::where('slug', 'how-crypto-profit-works')->first();
    expect($guide)->not->toBeNull()
        ->and($guide->calculators()->wherePivot('mode', 'embed')->count())->toBe(1);

    // The primary-Guide pointer is applied after all Posts are upserted.
    $profit = Calculator::where('slug', 'crypto-profit')->first();
    expect($profit->primary_post_id)->toBe($guide->getKey());
});

it('is idempotent — a second sync leaves the same row counts', function (): void {
    Artisan::call('content:sync');
    $calculators = Calculator::count();
    $posts = Post::count();
    $associations = DB::table('post_calculator')->count();

    Artisan::call('content:sync');

    expect(Calculator::count())->toBe($calculators)
        ->and(Post::count())->toBe($posts)
        ->and(DB::table('post_calculator')->count())->toBe($associations);
});
