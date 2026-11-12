<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** The application's shared calculator and Guide category index. */
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

    /** @return HasMany<Guide, $this> */
    public function guides(): HasMany
    {
        return $this->hasMany(Guide::class, 'category_id')->published();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
