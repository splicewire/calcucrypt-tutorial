<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PasswordConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirm_password_screen_can_be_rendered()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('password.confirm'));

        $response->assertOk();

        // theme-entries-and-authoring STR-03: every auth route renders the SAME beam-ux entry-resolution
        // page (`auth/entry`), differentiated by the `slug` prop — the sealed `ConfirmPassword` form
        // island renders inside its resolved tree (editor/registry.tsx), not as its own top-level page.
        $response->assertInertia(fn (Assert $page) => $page
            ->component('auth/entry')
            ->where('slug', 'confirm-password'),
        );
    }

    public function test_password_confirmation_requires_authentication()
    {
        $response = $this->get(route('password.confirm'));

        $response->assertRedirect(route('login'));
    }
}
