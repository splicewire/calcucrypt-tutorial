<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AssociationMode;
use App\Enums\PostStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * The Crypt's Post: a satellite-publishing Post that also carries editorial Associations
 * to Calculators. A Guide (user-facing) is exactly this — a Post that embeds and/or links
 * Calculators (CONTEXT.md "Article"). Pointed at by `config('splicewire.publishing.models.post')`.
 *
 * @property string $slug
 * @property string $title
 * @property string|null $teaser
 * @property Carbon|null $published_at
 * @property-read string|null $displayBody
 */
class Post extends Model
{
    use HasUuids;

    protected $fillable = [
        'slug', 'title', 'teaser', 'category_id', 'status', 'body_blocks', 'body_blocks_html', 'published_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => PostStatus::class,
            'body_blocks' => 'array',
            'published_at' => 'datetime',
        ];
    }

    /**
     * The rendered body. `content:sync` writes pre-rendered MDX into `body_blocks_html` and leaves
     * `body_blocks` as provenance (`['source' => 'mdx']`), so this reads the HTML column directly.
     * The retired base class had a richer accessor that could render blocks; nothing in this site
     * ever exercised that path — `GuideData` is the single consumer — so it is not reconstructed.
     */
    public function getDisplayBodyAttribute(): ?string
    {
        return $this->body_blocks_html;
    }

    /**
     * The instance form of {@see scopePublished()}, used by `GuideController::show()` to 404 an
     * unpublished Guide.
     *
     * Recovered the same way and mirrors {@see Calculator::isPublished()}, the surviving sibling.
     * The two must agree: a Guide the scope lists but this rejects is a link to a 404, and the
     * reverse is a leak.
     */
    public function isPublished(): bool
    {
        return $this->status === PostStatus::Published
            && $this->published_at !== null
            && ! $this->published_at->isFuture();
    }

    /**
     * A Guide the public may see: published status AND a `published_at` that has arrived.
     *
     * Recovered from the retired base class by its CALL SITES, not from any docblock — nine of them
     * across `GuideQuery`, `GuideSitemapSource`, `CalculatorController` and the `GuideController`
     * eager-load closure. It is the one piece of the base class that no attribute list or `fill()`
     * block revealed, and it is why the models compiling was not the same as the site working:
     * `/` returned 500 with "Call to undefined method Builder::published()" until this existed.
     *
     * Deliberately mirrors {@see Calculator::scopePublished()} — the sibling host-owned model whose
     * scope survived — minus its `expired_at` clause, which `posts` has no column for. Matching a
     * live sibling is the closest thing to the original semantics available now that the package is
     * gone.
     *
     * @param  Builder<Post>  $query
     * @return Builder<Post>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', PostStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * The Crypt's category, typed so calcucrypt reads its name/slug with static guarantees.
     *
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * The Calculators this Post embeds or links (Association, many-to-many). The pivot
     * carries the `mode` (embed|link), `position`, and `anchor` (a spot in the body).
     *
     * @return BelongsToMany<Calculator, $this>
     */
    public function calculators(): BelongsToMany
    {
        return $this->belongsToMany(Calculator::class, 'post_calculator')
            ->withPivot(['mode', 'position', 'anchor'])
            ->withTimestamps()
            ->orderByPivot('position');
    }

    /**
     * @return BelongsToMany<Calculator, $this>
     */
    public function embeddedCalculators(): BelongsToMany
    {
        return $this->calculators()->wherePivot('mode', AssociationMode::Embed->value);
    }

    /**
     * @return BelongsToMany<Calculator, $this>
     */
    public function linkedCalculators(): BelongsToMany
    {
        return $this->calculators()->wherePivot('mode', AssociationMode::Link->value);
    }
}
