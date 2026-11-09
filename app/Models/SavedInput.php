<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reader's named set of inputs for one calculator. The `saved-inputs` particle resource reads and writes it.
 *
 * @property string $id
 * @property string $user_id
 * @property string $calculator_id
 * @property string $name
 * @property array<string, mixed> $inputs
 * @property-read Calculator $calculator
 */
class SavedInput extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'inputs' => 'array',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Calculator, $this> */
    public function calculator(): BelongsTo
    {
        return $this->belongsTo(Calculator::class);
    }
}
