<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Content\Calculators\CalculatorDefinition;
use App\Content\Calculators\CalculatorRegistry;
use App\Content\CategoryCatalog;
use App\Enums\CalculatorStatus;
use App\Models\Calculator;
use App\Models\Category;
use App\Models\Post;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Enums\PostStatus;

/**
 * The single, idempotent path that materializes authored content — {@see CalculatorDefinition}
 * classes and precompiled MDX Guides — into the database. The database is a derived projection:
 * running this twice produces the same rows. Joins the post-deploy actions after `migrate`.
 */
class ContentSync extends Command
{
    protected $signature = 'content:sync';

    protected $description = 'Materialize authored calculators and Guides (the source of truth) into the database.';

    public function __construct(private readonly CalculatorRegistry $registry)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $definitions = $this->registry->all();
        $guides = $this->loadGuides();

        DB::transaction(function () use ($definitions, $guides): void {
            $categories = $this->ensureCategories($definitions, $guides);
            $calculators = $this->syncCalculators($definitions, $categories);
            $posts = $this->syncGuides($guides, $calculators, $categories);
            $this->applyPrimaryGuides($definitions, $calculators, $posts);
        });

        $this->info(sprintf('content:sync — %d calculators, %d guides.', count($definitions), count($guides)));

        return self::SUCCESS;
    }

    /**
     * @param  list<CalculatorDefinition>  $definitions
     * @param  list<array{slug: string, frontmatter: array<string, mixed>, html: string}>  $guides
     * @return array<string, Category>
     */
    private function ensureCategories(array $definitions, array $guides): array
    {
        $keys = [];
        foreach ($definitions as $definition) {
            $keys[] = $definition->category();
        }
        foreach ($guides as $guide) {
            $key = $guide['frontmatter']['category'] ?? null;
            if (is_string($key) && $key !== '') {
                $keys[] = $key;
            }
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
                'computation' => $definition->computation(),
                'entitlement' => $definition->entitlement(),
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
     * @param  list<array{slug: string, frontmatter: array<string, mixed>, html: string}>  $guides
     * @param  array<string, Calculator>  $calculators
     * @param  array<string, Category>  $categories
     * @return array<string, Post>
     */
    private function syncGuides(array $guides, array $calculators, array $categories): array
    {
        $posts = [];
        foreach ($guides as $guide) {
            $frontmatter = $guide['frontmatter'];
            $published = ($frontmatter['status'] ?? 'published') === 'published';

            $post = Post::query()->firstOrNew(['slug' => $guide['slug']]);
            $post->fill([
                'title' => (string) ($frontmatter['title'] ?? $guide['slug']),
                'teaser' => $frontmatter['teaser'] ?? null,
                'category_id' => $this->categoryId($frontmatter['category'] ?? null, $categories),
                'status' => $published ? PostStatus::Published : PostStatus::Draft,
                'body_blocks' => ['source' => 'mdx'],
                'body_blocks_html' => $guide['html'],
            ]);

            $publishedAt = $frontmatter['publishedAt'] ?? null;
            if (is_string($publishedAt) && $publishedAt !== '') {
                $post->published_at = Carbon::parse($publishedAt);
            } elseif ($published && $post->published_at === null) {
                $post->published_at = Carbon::now()->subDay();
            }

            $post->save();
            $this->syncAssociations($post, $frontmatter['calculators'] ?? [], $calculators);
            $posts[$guide['slug']] = $post;
        }

        return $posts;
    }

    /**
     * @param  array<int, array{slug: string, mode?: string, anchor?: string|null}>  $associations
     * @param  array<string, Calculator>  $calculators
     */
    private function syncAssociations(Post $post, array $associations, array $calculators): void
    {
        $pivot = [];
        foreach (array_values($associations) as $position => $association) {
            $calculator = $calculators[$association['slug']] ?? null;
            if ($calculator === null) {
                $this->warn(sprintf('  guide "%s" references unknown calculator "%s" — skipped.', $post->slug, $association['slug']));

                continue;
            }

            $pivot[$calculator->getKey()] = [
                'mode' => $association['mode'] ?? 'link',
                'anchor' => $association['anchor'] ?? null,
                'position' => $position,
            ];
        }

        $post->calculators()->sync($pivot);
    }

    /**
     * @param  list<CalculatorDefinition>  $definitions
     * @param  array<string, Calculator>  $calculators
     * @param  array<string, Post>  $posts
     */
    private function applyPrimaryGuides(array $definitions, array $calculators, array $posts): void
    {
        foreach ($definitions as $definition) {
            $guideSlug = $definition->primaryGuide();
            if ($guideSlug === null) {
                continue;
            }

            $calculator = $calculators[$definition->slug()] ?? null;
            $post = $posts[$guideSlug] ?? Post::query()->where('slug', $guideSlug)->first();
            if ($calculator === null || $post === null) {
                continue;
            }

            if ($calculator->primary_post_id !== $post->getKey()) {
                $calculator->primary_post_id = $post->getKey();
                $calculator->save();
            }
        }
    }

    /**
     * @param  array<string, Category>  $categories
     */
    private function categoryId(?string $key, array $categories): ?string
    {
        return $key !== null && isset($categories[$key]) ? $categories[$key]->getKey() : null;
    }

    /**
     * @return list<array{slug: string, frontmatter: array<string, mixed>, html: string}>
     */
    private function loadGuides(): array
    {
        $path = resource_path('content/compiled/guides.json');
        if (! is_file($path)) {
            $this->warn('content:sync — no compiled Guides artifact found; run `npm run guides:compile`. Skipping Guides.');

            return [];
        }

        /** @var list<array{slug: string, frontmatter: array<string, mixed>, html: string}> $guides */
        $guides = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        return $guides;
    }
}
