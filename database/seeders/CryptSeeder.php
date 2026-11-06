<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/**
 * Seeds the tutorial's catalogue. The authored calculators and Guides are materialized by
 * delegating to `content:sync`, the same idempotent command a deploy runs, so there is one
 * authoring path, not two.
 *
 * Calcucrypt also seeds baseline market Facts here so it works without a CoinMarketCap key. The
 * tutorial needs none: its default FixtureConnector prices every ref offline.
 */
class CryptSeeder extends Seeder
{
    public function run(): void
    {
        Artisan::call('content:sync');
    }
}
