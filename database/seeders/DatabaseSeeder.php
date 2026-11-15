<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * One call (seeding-scenarios 02): `splicewire:beam:seed` runs every step registered in the beam seed manifest, the
 * packages' and this app's own (`AppServiceProvider::registerSeedSteps()`: realm roots and nav, auth pages, theme,
 * the calculator catalogue, and the two sample users as a `demo` step). Every step is safe to repeat, so
 * `php artisan migrate --seed` can be run again after checking out a later commit. Which steps run is the Seeding Scenario's choice (`config/beam/seed.php`):
 * `local` and `preview` include `demo`; any other environment gets `baseline`.
 *
 * No `WithoutModelEvents`: this seeder's only work is the manifest run, and that trait muted the model events the
 * seeded entries are written through (the launch nomination of seeded pages 404ing at WWW, EP-42 H1, 2026-10-26).
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // The parent `db:seed --force` (or `migrate:fresh --seed`, which forwards it) is the production consent, and it
        // is forwarded DOWN: since beam-facade 190 `splicewire:beam:seed` does not force `db:seed` unconditionally.
        $exit = $this->command->call('splicewire:beam:seed', $this->command->option('force') ? ['--force' => true] : []);

        // A failed step makes `splicewire:beam:seed` exit non-zero; `Command::call()` only returns that code, so it is
        // rethrown here, or `db:seed` and `migrate --seed` would exit 0 over a failed seed (the app's own steps ran
        // through `$this->call()` and threw before they moved into the manifest).
        if ($exit !== 0) {
            throw new RuntimeException("splicewire:beam:seed exited {$exit}: a seed step failed (see its report above).");
        }
    }
}
