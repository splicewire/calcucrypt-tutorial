<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CalculatorStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;
use Splicewire\Beam\Accounts\Entitlements\BundleRegistry;

/**
 * A reusable, embeddable micro-tool modelled as a graph of Steps (CONTEXT.md "Calculator").
 * The graph lives in one JSON column; Locus is derived by the evaluator, never stored.
 *
 * @property string $slug
 * @property string $name
 * @property string|null $summary
 * @property string $computation
 * @property array<string, mixed> $graph
 * @property CalculatorStatus $status
 * @property string|null $entitlement the key a plan must hold to use it; null when it is free
 * @property string|null $primary_post_id
 * @property Carbon|null $published_at
 * @property Carbon|null $expired_at
 * @property-read Pivot $pivot  present when loaded via Post::calculators()
 */
class Calculator extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'graph' => 'array',
        'status' => CalculatorStatus::class,
        'published_at' => 'datetime',
        'expired_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * @return BelongsToMany<Post, $this>
     */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'post_calculator', 'calculator_id', 'post_id')
            ->withPivot(['mode', 'position', 'anchor'])
            ->withTimestamps();
    }

    /**
     * The canonical Guide that explains this calculator — the starting point surfaced first
     * in "Learn more". One of the referencing {@see posts()}; convention, not a DB constraint.
     *
     * @return BelongsTo<Post, $this>
     */
    public function primaryGuide(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'primary_post_id');
    }

    /**
     * The cheapest plan that unlocks this calculator: the first bundle declared in
     * `config/beam/accounts.php` that lists its entitlement. Null for a free calculator.
     */
    public function requiredPlan(): ?string
    {
        if ($this->entitlement === null) {
            return null;
        }

        $bundles = app(BundleRegistry::class);

        foreach ($bundles->names() as $plan) {
            if (in_array($this->entitlement, $bundles->keysFor($plan), true)) {
                return $plan;
            }
        }

        return null;
    }

    public function isPublished(): bool
    {
        return $this->status === CalculatorStatus::Published
            && $this->published_at !== null
            && $this->published_at->lte(now())
            && ($this->expired_at === null || $this->expired_at->gt(now()));
    }

    /**
     * The live calculators — published status AND inside the publish window. Mirrors
     * Post::scopePublished so a calculator and a guide gate identically.
     *
     * @param  Builder<Calculator>  $query
     * @return Builder<Calculator>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', CalculatorStatus::Published)
            ->where('published_at', '<=', now())
            ->where(function (Builder $query): void {
                $query->whereNull('expired_at')->orWhere('expired_at', '>', now());
            });
    }

    /**
     * Every market symbol this calculator can reference, gathered from each `ref` node's
     * `symbolFrom` input (all of its enum options, since the asset is user-selectable). The
     * feed watches exactly these so quota tracks what the Crypt actually shows.
     *
     * @return list<string>
     */
    public function referencedSymbols(): array
    {
        $nodes = $this->graph['nodes'] ?? [];
        $byId = [];
        foreach ($nodes as $node) {
            $byId[$node['id']] = $node;
        }

        $symbols = [];
        foreach ($nodes as $node) {
            if (($node['kind'] ?? null) !== 'ref') {
                continue;
            }

            $symbolFrom = $node['ref']['symbolFrom'] ?? null;

            // A vector ref (`over` a collection) draws its symbols from that collection's
            // field options and its seeded default rows.
            if (isset($node['over']) && isset($byId[$node['over']])) {
                $symbols = [...$symbols, ...$this->collectionSymbols($byId[$node['over']], (string) $symbolFrom)];
            } elseif ($symbolFrom !== null && isset($byId[$symbolFrom])) {
                $control = $byId[$symbolFrom]['control'] ?? [];
                foreach (($control['options'] ?? []) as $option) {
                    $symbols[] = strtoupper((string) $option);
                }
                if (isset($control['default'])) {
                    $symbols[] = strtoupper((string) $control['default']);
                }
            }

            if (isset($node['ref']['symbol'])) {
                $symbols[] = strtoupper((string) $node['ref']['symbol']);
            }
        }

        return array_values(array_unique($symbols));
    }

    /**
     * Symbols a collection node can hold: the `symbol` field's options unioned with the
     * symbols seeded into its default rows.
     *
     * @param  array<string, mixed>  $collection
     * @return list<string>
     */
    private function collectionSymbols(array $collection, string $symbolField): array
    {
        $control = $collection['control'] ?? [];
        $symbols = [];

        foreach (($control['fields'] ?? []) as $field) {
            if (($field['id'] ?? null) === $symbolField) {
                foreach (($field['options'] ?? []) as $option) {
                    $symbols[] = strtoupper((string) $option);
                }
            }
        }

        foreach (($control['default'] ?? []) as $row) {
            if (is_array($row) && isset($row[$symbolField])) {
                $symbols[] = strtoupper((string) $row[$symbolField]);
            }
        }

        return $symbols;
    }
}
