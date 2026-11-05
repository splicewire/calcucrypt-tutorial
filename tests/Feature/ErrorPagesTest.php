<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The branded error pages this host wires in `bootstrap/app.php` (`ErrorPages::register()`, from
 * splicewire/laravel-beam-accounts): a browser's 403/404/419/500/503 renders the packaged `error` page
 * with the original status, and every JSON error response is unchanged.
 *
 * The probe routes are registered here rather than borrowed from a realm gate, so the assertion is
 * about the exception handler's wiring and not about which realm this starter happens to gate.
 */
class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // AHEAD of the host's own routes: a public-entry catch-all (`{path}`) matches first otherwise.
        $existing = Route::getRoutes();
        Route::setRoutes(new RouteCollection);

        Route::middleware('web')->group(function (): void {
            Route::get('__error-probe/policy', fn () => throw new AuthorizationException);
            Route::get('__error-probe/{status}', fn (int $status) => abort($status));
        });
        Route::get('api/__error-probe/policy', fn () => throw new AuthorizationException);

        foreach ($existing->getRoutes() as $route) {
            Route::getRoutes()->add($route);
        }
    }

    public function test_a_signed_in_refusal_renders_the_error_page_with_the_policy_sentence(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/__error-probe/policy')
            ->assertStatus(403)
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('error')
                ->where('status', 403)
                ->where('message', 'This action is unauthorized.')
                // The page picks the account shell from this shared prop.
                ->whereNot('auth.user', null));
    }

    public function test_a_guest_404_419_and_503_keep_their_status(): void
    {
        foreach ([404, 419, 503] as $status) {
            $this->get("/__error-probe/{$status}")
                ->assertStatus($status)
                ->assertInertia(fn (AssertableInertia $page) => $page
                    ->component('error')
                    ->where('status', $status));
        }
    }

    public function test_json_error_responses_are_unchanged(): void
    {
        $this->getJson('/__error-probe/policy')
            ->assertStatus(403)
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('message', 'This action is unauthorized.');

        // `api/*` is JSON by this host's `shouldRenderJsonWhen` even without an Accept header.
        $this->get('/api/__error-probe/policy')
            ->assertStatus(403)
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('message', 'This action is unauthorized.');
    }
}
