<?php

namespace Tests\Feature;

use App\Models\Calculator;
use App\Models\User;
use Database\Seeders\AuthPagesSeeder;
use Database\Seeders\CryptSeeder;
use Database\Seeders\DemoUsersSeeder;
use Database\Seeders\RealmNavSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Splicewire\Beam\Seed\BeamSeedManifest;
use Splicewire\Beam\Seed\SeedStep;
use Splicewire\Beam\Ux\Seeders\ThemeSeeder;
use Tests\TestCase;

/**
 * seeding-scenarios 02: the tutorial's own seed data, the catalogue among it, runs as host steps in the beam seed manifest, and
 * `DatabaseSeeder` is the one `splicewire:beam:seed` call, safe to repeat.
 */
class SeedStepsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_host_steps_are_registered_below_the_demo_team_under_the_starters_name(): void
    {
        $steps = collect(app(BeamSeedManifest::class)->steps())->keyBy('package');

        $expected = [
            'splicewire/calcucrypt-tutorial/realm-nav' => [RealmNavSeeder::class, [SeedStep::BASELINE]],
            'splicewire/calcucrypt-tutorial/auth-pages' => [AuthPagesSeeder::class, [SeedStep::BASELINE]],
            'splicewire/calcucrypt-tutorial/theme' => [ThemeSeeder::class, [SeedStep::BASELINE]],
            'splicewire/calcucrypt-tutorial/demo-users' => [DemoUsersSeeder::class, [SeedStep::DEMO]],
        ];

        // The catalogue (content:sync) is a baseline step after the packages' own (default order).
        $this->assertSame(CryptSeeder::class, $steps['splicewire/calcucrypt-tutorial/catalogue']->seeder);
        $this->assertSame([SeedStep::BASELINE], $steps['splicewire/calcucrypt-tutorial/catalogue']->memberships);

        foreach ($expected as $coordinate => [$seeder, $memberships]) {
            $this->assertTrue($steps->has($coordinate), "{$coordinate} is registered");
            $this->assertSame($seeder, $steps[$coordinate]->seeder);
            $this->assertSame($memberships, $steps[$coordinate]->memberships);
            $this->assertLessThan(10, $steps[$coordinate]->order, "{$coordinate} runs before beam-accounts' demo team");
        }
    }

    public function test_db_seed_passes_twice_and_the_second_run_rewrites_no_seeded_entry(): void
    {
        $this->app['env'] = 'local';

        $this->artisan('db:seed')->assertExitCode(0);
        $first = $this->snapshot();

        $this->artisan('db:seed')->assertExitCode(0);

        $this->assertSame($first, $this->snapshot());
        $this->assertSame(1, User::query()->where('email', 'test@example.com')->count());
        $this->assertSame(1, User::query()->where('email', 'staff@example.com')->count());
        $this->assertGreaterThan(0, count($first['entries']), 'the auth pages, theme and nav were seeded');
        $this->assertGreaterThan(0, Calculator::query()->count(), 'the catalogue was seeded');
    }

    public function test_the_baseline_scenario_seeds_the_host_data_and_no_sample_users(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('db:seed', ['--force' => true])->assertExitCode(0);

        $this->assertSame(0, User::query()->whereIn('email', ['test@example.com', 'staff@example.com'])->count());
        $this->assertGreaterThan(0, DB::table('beam_ux_entries')->count());
    }

    /** @return array{entries: list<array<string, mixed>>, users: int} */
    private function snapshot(): array
    {
        return [
            'entries' => DB::table('beam_ux_entries')->orderBy('id')->get(['id', 'asserted_hash'])
                ->map(fn ($row): array => (array) $row)->all(),
            'users' => User::query()->count(),
        ];
    }
}
