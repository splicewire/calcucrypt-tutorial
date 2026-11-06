<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A stored Live Fact `{ value, source, as_of, connector }` — a time-stamped market
 * observation. Freshness and provenance are intrinsic (they come from the source payload),
 * which is what lets the Crypt promise "never stale, always cited".
 *
 * @property string $key
 * @property string $connector
 * @property string $metric
 * @property string $symbol
 * @property string $value
 * @property string $source
 * @property Carbon $as_of
 * @property array<string, mixed>|null $raw
 */
class Fact extends Model
{
    protected $guarded = [];

    protected $casts = [
        'as_of' => 'datetime',
        'raw' => 'array',
    ];

    public static function keyFor(string $connector, string $metric, string $symbol): string
    {
        return "{$connector}:{$metric}:{$symbol}";
    }

    public function floatValue(): float
    {
        return (float) $this->value;
    }
}
