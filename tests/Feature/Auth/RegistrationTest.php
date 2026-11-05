<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Splicewire\Beam\Accounts\Fortify\CreateNewUser;
use Tests\TestCase;

/**
 * purchase-walkthrough BUY-05 (M10): with ACCOUNT_REGISTRATION=open, this starter's registration door is open, and a
 * registrant is created through beam-accounts' door-checked action, not a host copy.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected ?string $accountRegistration = 'open';

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register()
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'passwordConfirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_a_registrant_is_created_through_the_door_checked_action()
    {
        $this->assertInstanceOf(CreateNewUser::class, app(CreatesNewUsers::class));
        $this->assertFileDoesNotExist(app_path('Actions/Fortify/CreateNewUser.php'));
    }
}
