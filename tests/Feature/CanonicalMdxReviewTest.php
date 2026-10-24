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
        config(['canonical-mdx.enabled' => true]);
        Route::middleware('web')->group(base_path('routes/canonical-mdx.php'));
        $this->mock(EntryPublication::class)->shouldNotReceive('state');
        $path = '/account/canonical-mdx/00000000-0000-4000-8000-000000000001/review';
        $this->get($path)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get($path)->assertForbidden();
    }
}
