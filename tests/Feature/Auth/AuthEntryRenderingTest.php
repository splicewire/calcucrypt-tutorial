<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\AuthPagesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Splicewire\Beam\Models\BeamParticle;
use Splicewire\Beam\Ux\Models\BeamUxEntry;
use Splicewire\Beam\Ux\Support\EntryBody;
use Tests\TestCase;

/**
 * theme-entries-and-authoring STR-03: every promoted auth route renders the SAME beam-ux
 * entry-resolution page (`auth/entry`), differentiated by `slug` — pins that contract for every route
 * beyond the 2 already covered inline in {@see PasswordConfirmationTest}/{@see TwoFactorChallengeTest},
 * plus the two new pieces this ticket added: `Splicewire\Beam\Ux\Support\EntryBody`'s degrade path and
 * `AuthPagesSeeder`'s idempotency.
 */
class AuthEntryRenderingTest extends TestCase
{
    use RefreshDatabase;

    /** Registration is closed by default (BUY-05); the register page renders only behind an open door. */
    protected ?string $accountRegistration = 'open';

    public function test_login_renders_the_auth_entry_page_with_the_login_slug(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/entry')
                ->where('slug', 'login')
            );
    }

    public function test_register_renders_the_auth_entry_page_with_the_register_slug(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/entry')
                ->where('slug', 'register')
            );
    }

    public function test_forgot_password_renders_the_auth_entry_page_with_its_slug(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/entry')
                ->where('slug', 'forgot-password')
            );
    }

    public function test_reset_password_renders_the_auth_entry_page_with_its_slug(): void
    {
        $this->get(route('password.reset', ['token' => 'a-token', 'email' => 'test@example.com']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/entry')
                ->where('slug', 'reset-password')
            );
    }

    public function test_verify_email_renders_the_auth_entry_page_with_its_slug(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get(route('verification.notice'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/entry')
                ->where('slug', 'verify-email')
            );
    }

    public function test_entry_body_degrades_to_null_when_no_entry_row_exists(): void
    {
        $this->assertNull(app(EntryBody::class)->forSlug('login'));
    }

    public function test_entry_body_degrades_to_null_when_the_entry_has_no_particle(): void
    {
        BeamUxEntry::create([
            'namespace' => config('beam.ux.namespace', 'starter'),
            'slug' => 'login',
            'type' => 'page',
            'realm' => 'auth',
        ]);

        $this->assertNull(app(EntryBody::class)->forSlug('login'));
    }

    public function test_entry_body_resolves_a_seeded_entrys_body(): void
    {
        (new AuthPagesSeeder)->run();

        $body = app(EntryBody::class)->forSlug('login');

        $this->assertIsArray($body);
        $this->assertSame('AuthLogin', collect($body)->last()['name']);
    }

    public function test_auth_pages_seeder_is_idempotent_no_orphaned_particles(): void
    {
        (new AuthPagesSeeder)->run();
        $firstParticleId = BeamUxEntry::query()
            ->where('namespace', config('beam.ux.namespace', 'starter'))
            ->where('slug', 'login')
            ->value('particle_id');

        (new AuthPagesSeeder)->run();

        $entries = BeamUxEntry::query()
            ->where('namespace', config('beam.ux.namespace', 'starter'))
            ->where('slug', 'login')
            ->get();

        $this->assertCount(1, $entries);
        $this->assertSame($firstParticleId, $entries->first()->particle_id);
        // 7 pages seeded, one particle each — re-running must refresh in place, never mint an 8th.
        $this->assertSame(7, BeamParticle::query()->count());
    }

    public function test_fresh_auth_seed_publishes_every_page_and_rerun_preserves_identity(): void
    {
        $seeder = new AuthPagesSeeder;
        $seeder->run();
        $pages = BeamUxEntry::query()->where('realm', 'auth')->whereNotNull('particle_id')->orderBy('slug')->get();
        $this->assertCount(7, $pages);
        $publication = app(\Splicewire\Beam\Ux\Publish\EntryPublication::class);
        foreach ($pages as $entry) {
            $this->assertNotNull($publication->publishedVersionId($entry), $entry->slug);
        }
        $identities = $pages->mapWithKeys(fn ($entry) => [$entry->slug => [$entry->id, $entry->particle_id]])->all();
        $seeder->run();
        $again = BeamUxEntry::query()->where('realm', 'auth')->whereNotNull('particle_id')->orderBy('slug')->get();
        $this->assertSame($identities, $again->mapWithKeys(fn ($entry) => [$entry->slug => [$entry->id, $entry->particle_id]])->all());
        $this->assertSame(7, BeamParticle::query()->count());
        foreach ($again as $entry) {
            $this->assertNotNull($publication->publishedVersionId($entry), $entry->slug);
        }

        $themeSeeder = new \Splicewire\Beam\Ux\Seeders\ThemeSeeder;
        $themeSeeder->run();
        $theme = BeamUxEntry::query()->where('namespace', 'theme')->where('slug', 'default')->firstOrFail();
        $this->assertNotNull($publication->publishedVersionId($theme), 'theme/default');
        $themeIdentity = [$theme->id, $theme->particle_id];
        $themeSeeder->run();
        $theme->refresh();
        $this->assertSame($themeIdentity, [$theme->id, $theme->particle_id]);
        $this->assertNotNull($publication->publishedVersionId($theme), 'theme/default rerun');
        // EP-01 owns these eight bodies; the three docs/MCP entries are a separate nomination.
        $this->assertSame(8, BeamParticle::query()->count());
    }
}
