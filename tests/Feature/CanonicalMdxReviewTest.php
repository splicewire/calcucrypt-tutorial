<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Splicewire\Beam\Ux\Publish\EntryPublication;
use Tests\TestCase;

final class CanonicalMdxReviewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->artisan('migrate:fresh')->assertExitCode(0);
        $this->withoutVite();
    }

    public function test_teaching_route_and_review_policy_are_unchanged_without_opt_in(): void
    {
        $this->assertFalse(config('canonical-mdx.enabled'));
        $this->assertFalse(Route::has('canonical-mdx.review'));
        $this->assertFalse(config('beam.ux.releases.review_required'));
        $this->get('/account/canonical-mdx/00000000-0000-4000-8000-000000000001/review')->assertNotFound();
    }

    public function test_guest_and_unprivileged_actor_cannot_read_saved_choices(): void
    {
        $previousEnv = $_ENV['BEAM_CANONICAL_MDX_REVIEW_ENABLED'] ?? null;
        $previousServer = $_SERVER['BEAM_CANONICAL_MDX_REVIEW_ENABLED'] ?? null;
        try {
            // Select the documented opt-in BEFORE routes register, not behind the entry catch-all.
            $_ENV['BEAM_CANONICAL_MDX_REVIEW_ENABLED'] = 'true';
            $_SERVER['BEAM_CANONICAL_MDX_REVIEW_ENABLED'] = 'true';
            $this->refreshApplication();
            $this->assertSame(':memory:', config('database.connections.sqlite.database'));
            $this->artisan('migrate:fresh')->assertExitCode(0);
            $this->withoutVite();
            $this->assertTrue(config('canonical-mdx.enabled'));
            $this->assertTrue(Route::has('canonical-mdx.review'));
            $this->mock(EntryPublication::class)->shouldNotReceive('state');
            $path = '/account/canonical-mdx/00000000-0000-4000-8000-000000000001/review';
            $this->get($path)->assertRedirect(route('login'));
            $this->actingAs(User::factory()->create())->get($path)->assertForbidden();
        } finally {
            if ($previousEnv === null) {
                unset($_ENV['BEAM_CANONICAL_MDX_REVIEW_ENABLED']);
            } else {
                $_ENV['BEAM_CANONICAL_MDX_REVIEW_ENABLED'] = $previousEnv;
            }
            if ($previousServer === null) {
                unset($_SERVER['BEAM_CANONICAL_MDX_REVIEW_ENABLED']);
            } else {
                $_SERVER['BEAM_CANONICAL_MDX_REVIEW_ENABLED'] = $previousServer;
            }
        }
    }
}
