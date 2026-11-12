<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_page_is_displayed()
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);
        Features::passkeys([
            'confirmPassword' => true,
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/security')
                ->where('canManagePasskeys', true)
                ->where('passkeys', [])
                ->where('canManageTwoFactor', true)
                ->where('twoFactorEnabled', false),
            );
    }

    public function test_security_page_requires_password_confirmation_when_enabled()
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        $user = User::factory()->create();

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);

        $response = $this->actingAs($user)
            ->get(route('security.edit'));

        $response->assertRedirect(route('password.confirm'));
    }

    public function test_security_page_renders_without_two_factor_when_feature_is_disabled()
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        config(['fortify.features' => []]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/security')
                ->where('canManagePasskeys', false)
                ->where('passkeys', [])
                ->where('canManageTwoFactor', false)
                ->missing('twoFactorEnabled')
                ->missing('requiresConfirmation'),
            );
    }

    public function test_password_can_be_updated()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('security.edit'))
            ->put(route('user-password.update'), [
                'currentPassword' => 'password',
                'password' => 'new-password',
                'passwordConfirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('security.edit'));

        $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
    }

    public function test_correct_password_must_be_provided_to_update_password()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('security.edit'))
            ->put(route('user-password.update'), [
                'currentPassword' => 'wrong-password',
                'password' => 'new-password',
                'passwordConfirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasErrors('currentPassword')
            ->assertRedirect(route('security.edit'));
    }

    public function test_password_confirmation_must_match()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('security.edit'))
            ->put(route('user-password.update'), [
                'currentPassword' => 'password',
                'password' => 'new-password',
                'passwordConfirmation' => 'different-password',
            ])
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('password', $user->refresh()->password));
    }

    // The settings wire is camel-only (beam-inertia's security form): the legacy snake keys are refused, not read.
    public function test_the_legacy_snake_keys_are_refused()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('security.edit'))
            ->put(route('user-password.update'), [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertSessionHasErrors([
                'current_password' => 'Unknown input field [current_password].',
                'password_confirmation' => 'Unknown input field [password_confirmation].',
            ])
            ->assertSessionDoesntHaveErrors('currentPassword');

        $this->assertTrue(Hash::check('password', $user->refresh()->password));
    }

    public function test_password_update_preserves_its_success_toast(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('user-password.update'), [
            'currentPassword' => 'password',
            'password' => 'new-password',
            'passwordConfirmation' => 'new-password',
        ])->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Password updated.'])
            ->assertSessionHas('status', 'password-updated');
    }

    public function test_password_update_refuses_unknown_fields_without_changing_the_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('user-password.update'), [
            'currentPassword' => 'password',
            'password' => 'new-password',
            'passwordConfirmation' => 'new-password',
            'unexpected' => 'ignored by the old request',
        ])->assertSessionHasErrors(['unexpected' => 'Unknown input field [unexpected].']);

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }
}
