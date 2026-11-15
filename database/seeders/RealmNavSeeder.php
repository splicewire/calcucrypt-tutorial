<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/**
 * Every realm's root entry and its nav (theme-entries-and-authoring), as a host seed step. It runs ahead of
 * beam-accounts' demo team (order 10): the grant cascade (`DefaultEntitlementResolver`, ACC-01) only composes a realm
 * key a grantee holds `manage` on for a REAL root row, so the Demo Team's grant walk needs these rows first.
 * `splicewire:beam:ux:seed-nav` is idempotent (`updateOrCreate`).
 */
class RealmNavSeeder extends Seeder
{
    public function run(): void
    {
        Artisan::call('splicewire:beam:ux:seed-nav');
    }
}
