<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Content\Calculators\CalculatorDefinition;
use App\Content\Calculators\CalculatorRegistry;
use App\Content\CategoryCatalog;
use App\Content\Guides\GuideImporter;
use App\Enums\CalculatorStatus;
use App\Models\Calculator;
use App\Models\Category;
use App\Models\Guide;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Materializes calculator definitions and initially registers authored Guides in UX.
 * Repeated unchanged files preserve UX edits and publication state; changed files refuse
 * explicitly so an operator can reconcile them through UX instead of overwriting an author.
 */
class ContentSync extends Command
{
    protected $signature = 'content:sync';

    protected $description = 'Sync calculators and initially register Guides; refuse changed Guide source.';

    public function __construct(private readonly CalculatorRegistry $registry)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $definitions = $this->registry->all();

        DB::transaction(function () use ($definitions): void {
            $categories = $this->ensureCategories($definitions);
            $calculators = $this->syncCalculators($definitions, $categories);
            app(GuideImporter::class)->import();
            $guides = Guide::query()->published()->get()->keyBy('slug')->all();
            $this->applyPrimaryGuides($definitions, $calculators, $guides);
        });

        $this->info(sprintf('content:sync — %d calculators, %d guides.', count($definitions), Guide::query()->published()->count()));

        return self::SUCCESS;
    }

    /**
     * @param  list<CalculatorDefinition>  $definitions
     * @return array<string, Category>
     */
    private function ensureCategories(array $definitions): array
    {
        $keys = [];
        foreach ($definitions as $definition) {
            $keys[] = $definition->category();
        }

        $categories = [];
        foreach (array_unique($keys) as $key) {
            $categories[$key] = Category::query()->firstOrCreate(
                ['slug' => $key],
                ['name' => CategoryCatalog::nameFor($key)],
            );
        }

        return $categories;
    }

    /**
     * @param  list<CalculatorDefinition>  $definitions
     * @param  array<string, Category>  $categories
     * @return array<string, Calculator>
     */
    private function syncCalculators(array $definitions, array $categories): array
    {
        $calculators = [];
        foreach ($definitions as $definition) {
            $calculator = Calculator::query()->firstOrNew(['slug' => $definition->slug()]);
            $calculator->fill([
                'name' => $definition->name(),
                'summary' => $definition->summary(),
                'key' => $definition->key(),
                'tags' => $definition->tags(),
                'category_id' => $categories[$definition->category()]->getKey(),
                'status' => $definition->status(),
                'graph' => $definition->graph(),
            ]);

            // Deterministic on re-sync: only stamp a publish moment when the row first appears.
            if ($definition->status() === CalculatorStatus::Published && $calculator->published_at === null) {
                $calculator->published_at = Carbon::now()->subDay();
            }

            $calculator->save();
            $calculators[$definition->slug()] = $calculator;
        }

        return $calculators;
    }

    /**
     * @param  list<CalculatorDefinition>  $definitions
     * @param  array<string, Calculator>  $calculators
     * @param  array<string, Guide>  $guides
     */
    private function applyPrimaryGuides(array $definitions, array $calculators, array $guides): void
    {
        foreach ($definitions as $definition) {
            $guideSlug = $definition->primaryGuide();
            if ($guideSlug === null) {
                continue;
            }

            $calculator = $calculators[$definition->slug()] ?? null;
            $guide = $guides[$guideSlug] ?? Guide::query()->published()->where('slug', $guideSlug)->first();
            if ($calculator === null || $guide === null) {
                continue;
            }

            if ($calculator->primary_guide_id !== $guide->getKey()) {
                $calculator->primary_guide_id = $guide->getKey();
                $calculator->save();
            }
        }
    }
}
