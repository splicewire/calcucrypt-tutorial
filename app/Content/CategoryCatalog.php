<?php

declare(strict_types=1);

namespace App\Content;

use Illuminate\Support\Str;

/**
 * The Crypt's taxonomy, authored as source. A category key doubles as its slug; this maps
 * the key to a display name so `content:sync` can ensure every category a calculator or
 * Guide references exists.
 */
class CategoryCatalog
{
    /**
     * @var array<string, string>
     */
    public const NAMES = [
        'profit' => 'Profit',
        'staking' => 'Staking',
        'dca' => 'DCA',
        'converter' => 'Converter',
        'allocation' => 'Allocation',
        'tax' => 'Tax',
        'trader' => 'Trader',
    ];

    public static function nameFor(string $key): string
    {
        return self::NAMES[$key] ?? Str::title($key);
    }
}
