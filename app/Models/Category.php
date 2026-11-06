<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The Crypt's Category — the publishing taxonomy a Guide is filed under.
 *
 * ## Why this is a plain model now
 *
 * It used to be `extends Splicewire\Beam\Publishing\Models\Category`. That class never existed:
 * `432677c` repointed the imports from `Splicewire\Satellite\Publishing\*` to a `Beam\Publishing\*`
 * successor whose ticket recorded "own git repo (local); GitHub create = external gate" — and that
 * gate was never cleared. Then `adcc428` dropped the old package from `composer.json` while touching
 * only that file and `AppServiceProvider`, leaving three app files importing a namespace with nothing
 * behind it. Neither `splicewire/laravel-satellite-publishing` nor a `laravel-beam-publishing`
 * exists on disk or on GitHub today.
 *
 * The site's front door returned **HTTP 500 from 2026-08-05 until this was inlined**, and nothing
 * noticed: `artisan about` and `route:list` both exit 0 because Laravel never autoloads this class
 * for either.
 *
 * So the plane is host-owned now. That is a real decision, not a workaround — calcucrypt is the only
 * consumer left, and a two-model taxonomy is not worth a package.
 *
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string|null $parent_id
 */
class Category extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'slug', 'parent_id'];

    /** @return BelongsTo<Category, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<Category, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return HasMany<Post, $this> */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'category_id');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
