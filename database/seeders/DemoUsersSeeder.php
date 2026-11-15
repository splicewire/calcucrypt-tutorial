<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * The starter's two sample users, as a `demo` seed step (seeding-scenarios 02): a Seeding Scenario that includes
 * `demo` seeds them (`local` and `preview` here), and `baseline` never does. Find-or-create by email, so a second seed
 * changes nothing (`users.email` is unique, and the old unconditional `create()` threw on a re-seed).
 *
 * - **Test User:** a non-staff user. It sees the public and account realms, but not operator/OS (hard-gated).
 * - **Staff User:** real operator/authoring reach is a Team's realm-root `manage` grant (ACC-01's cascade). The
 *   `is_staff` column is gone (particle-identity-resources ticket 04).
 */
class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        if (! User::query()->where('email', 'test@example.com')->exists()) {
            User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
        }

        if (! User::query()->where('email', 'staff@example.com')->exists()) {
            User::factory()->staff()->create([
                'name' => 'Staff User',
                'email' => 'staff@example.com',
            ]);
        }
    }
}
