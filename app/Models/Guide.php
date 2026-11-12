<?php

namespace App\Models;

use App\Content\Guides\GuideCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Splicewire\Beam\Ux\Models\BeamUxEntry;

/**
 * A disposable search and relationship index of a visible, pinned UX Guide.
 *
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property-read BeamUxEntry $entry
 * @property-read Category|null $category
 */
class Guide extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return BelongsTo<BeamUxEntry, $this> */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(BeamUxEntry::class, 'entry_id');
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsToMany<Calculator, $this> */
    public function calculators(): BelongsToMany
    {
        return $this->belongsToMany(Calculator::class, 'calculator_guide')->withPivot(['mode', 'position', 'anchor'])->withTimestamps()->orderByPivot('position');
    }

    /** @param Builder<Guide> $query
     * @return Builder<Guide>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereIn('entry_id', app(GuideCatalog::class)->visibleEntryIds());
    }

    public function isPublished(): bool
    {
        return in_array($this->entry_id, app(GuideCatalog::class)->visibleEntryIds(), true);
    }
}
