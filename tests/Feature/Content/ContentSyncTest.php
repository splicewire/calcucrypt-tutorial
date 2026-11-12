<?php

declare(strict_types=1);

use App\Models\Calculator;
use App\Models\Guide;
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

    $guide = Guide::where('slug', 'how-crypto-profit-works')->first();
    expect($guide)->not->toBeNull()
        ->and($guide->calculators()->wherePivot('mode', 'embed')->count())->toBe(1);

    // The primary-Guide pointer is applied after all Guides are indexed.
    $profit = Calculator::where('slug', 'crypto-profit')->first();
    expect($profit->primary_guide_id)->toBe($guide->getKey());
});

it('is idempotent — a second sync leaves the same row counts', function (): void {
    Artisan::call('content:sync');
    $calculators = Calculator::count();
    $posts = Guide::count();
    $associations = DB::table('calculator_guide')->count();

    Artisan::call('content:sync');

    expect(Calculator::count())->toBe($calculators)
        ->and(Guide::count())->toBe($posts)
        ->and(DB::table('calculator_guide')->count())->toBe($associations);
});

it('preserves every authored association tuple in order across both imports', function (): void {
    $expected = [];
    foreach (glob(resource_path('content/guides/*.mdx')) as $file) {
        $metadata = app(App\Content\Guides\GuideImporter::class)->check(file_get_contents($file), array_map(fn ($definition) => $definition->slug(), app(App\Content\Calculators\CalculatorRegistry::class)->all()));
        $expected[basename($file, '.mdx')] = array_map(fn ($association, $position) => [$association->slug, $association->mode, $association->anchor, $position], $metadata->calculators, array_keys($metadata->calculators));
    }
    foreach ([1, 2] as $import) {
        Artisan::call('content:sync');
        foreach ($expected as $slug => $tuples) {
            $actual = Guide::query()->where('slug', $slug)->firstOrFail()->calculators->map(fn ($calculator) => [$calculator->slug, $calculator->pivot->mode, $calculator->pivot->anchor, $calculator->pivot->position])->all();
            expect($actual)->toBe($tuples);
        }
    }
});
