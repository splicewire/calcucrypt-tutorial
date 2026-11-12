<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Splicewire\Beam\Accounts\Enums\Role;
use Splicewire\Beam\Accounts\Models\Membership;
use Splicewire\Beam\Accounts\Models\Team;
use Tests\TestCase;

/**
 * frame-foundation 51: the tokens page writes no URL. Its route hands it the endpoints laravel-beam-accounts builds
 * from its own mounted routes (`TokensEndpoints::props()`), and the page passes them to `createTokensClient`, so the
 * page follows `ACCOUNT_API_ROOT` wherever the host mounts it.
 */
class AccountTokensEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_tokens_page_receives_the_mounted_token_routes_as_its_endpoints(): void
    {
        $this->withoutVite();
        $member = User::factory()->create();
        $team = Team::create(['user_id' => $member->id, 'name' => 'Mine', 'personal_team' => true]);
        Membership::create(['team_id' => $team->id, 'user_id' => $member->id, 'role' => Role::Owner->value]);
        $member->fresh()->switchTeam($team);

        $root = '/'.trim(config('beam.accounts.api_root'), '/');

        $this->actingAs($member->fresh())->get(route('account.tokens'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('account/tokens', shouldExist: false)
                ->where('endpoints', [
                    'index' => "{$root}/tokens",
                    'permissions' => "{$root}/tokens/permissions",
                    'sessionsOthers' => "{$root}/tokens/sessions/others",
                ]));

        // The roster the page lists is reachable at the endpoint it was handed.
        $this->actingAs($member->fresh())->getJson("{$root}/tokens")->assertOk();
    }

    public function test_the_page_follows_a_changed_mounted_root_and_guest_refusal_remains(): void
    {
        $this->withoutVite();
        config(['beam.accounts.api_root' => 'proof/accounts']);
        // Replace the old API mount in a fresh route table, keeping it ahead of the site catch-all.
        $originalRoutes = \Illuminate\Support\Facades\Route::getRoutes()->getRoutes();
        \Illuminate\Support\Facades\Route::setRoutes(new \Illuminate\Routing\RouteCollection);
        \Illuminate\Support\Facades\Route::splicewireAccountApiRoutes();
        foreach ($originalRoutes as $route) {
            if (! str_starts_with((string) $route->getName(), 'beam.accounts.')) {
                \Illuminate\Support\Facades\Route::getRoutes()->add($route);
            }
        }
        \Illuminate\Support\Facades\Route::getRoutes()->refreshNameLookups();
        $this->getJson('/beam/accounts/tokens')->assertNotFound();
        $this->get(route('account.tokens'))->assertRedirect(route('login'));
        $this->getJson('/proof/accounts/tokens')->assertUnauthorized();
        $member = User::factory()->create();
        $this->actingAs($member)->get(route('account.tokens'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('account/tokens', shouldExist: false)
                ->where('endpoints', [
                    'index' => '/proof/accounts/tokens',
                    'permissions' => '/proof/accounts/tokens/permissions',
                    'sessionsOthers' => '/proof/accounts/tokens/sessions/others',
                ]));
        $this->actingAs($member)->getJson('/proof/accounts/tokens')->assertOk();
    }
}
