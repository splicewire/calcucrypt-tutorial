<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Splicewire\Beam\Ux\Seeders\ThemeSeeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // A non-staff demo user — sees the public/account realms, but NOT operator/OS (hard-gated).
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Every realm's root entry, FIRST (theme-entries-and-authoring): the grant-cascade
        // (`DefaultEntitlementResolver`, ACC-01) only composes a realm key a grantee holds `manage` on
        // a REAL root row for — `splicewire:beam:seed` below runs beam-accounts' DemoTeamSeeder BEFORE
        // beam-ux's own NavSeeder (its manifest's own internal order, not something this host
        // controls), so without this explicit call the Demo Team's grant walk would see zero
        // provisioned realms on a fresh install. `AuthPagesSeeder` provisions its own `auth` realm root
        // the same way (nothing else does — it has no nav.yml entry).
        Artisan::call('splicewire:beam:ux:seed-nav');
        $this->call(AuthPagesSeeder::class);

        // Run EVERY beam-* package's registered seeder from ONE command (the package-registered seed
        // manifest): beam-accounts contributes its DemoTeamSeeder (gated by beam.accounts.demo.seed_users →
        // on outside production), beam-ux its content-nav seeder (redundant here, harmless — idempotent
        // `updateOrCreate`), etc. This host no longer hand-calls any package seeder by class — a new
        // beam package's data lands automatically once it registers.
        //
        // The accounts DemoTeamSeeder creates the demo USERS (all this app needs for login-as via the
        // login page's quick-login buttons), assigns team ROLES, AND grants the shared "Demo Team"
        // `manage` on every provisioned realm root (ACC-01) — so the Owner/Admin demo subjects
        // (`Role::grantEligible()`) already reach `/operator`/`/os` and author-ux with zero further
        // seeding here; nothing needs a manual `is_staff` flip anymore.
        // (Invoked as an artisan COMMAND, not Seeder::call — the manifest, not this host, names the seeders.)
        // The parent `db:seed --force` (or `migrate:fresh --seed`, which forwards it) is the production
        // consent, and it is forwarded DOWN: since beam-facade 190 `splicewire:beam:seed` no longer forces
        // `db:seed` unconditionally, so without this a production seed declines every beam seeder.
        $this->command->call('splicewire:beam:seed', $this->command->option('force') ? ['--force' => true] : []);

        // A staff user — theme-entries-and-authoring: real operator/authoring reach is a Team's
        // realm-root `manage` grant (ACC-01's cascade). The `is_staff` column is gone entirely
        // (particle-identity-resources ticket 04) — this host's Gates/routes never read it, and
        // `DefaultEntitlementResolver`, the package default this host rides unmodified, never has.
        User::factory()->staff()->create([
            'name' => 'Staff User',
            'email' => 'staff@example.com',
        ]);

        // This host's central theme entry — today's shipped canvas/shell/site values, so migrate+seed
        // renders unchanged output before any author touches the theme (theme-entries-and-authoring
        // ticket str-01).
        $this->call(ThemeSeeder::class);
    }
}
