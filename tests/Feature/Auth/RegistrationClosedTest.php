<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** purchase-walkthrough BUY-05 (M10, rule BUY-9): a closed registration door mounts no route, so it 404s and creates no one. */
class RegistrationClosedTest extends TestCase
{
    use RefreshDatabase;

    protected ?string $accountRegistration = 'closed';

    public function test_a_closed_door_is_not_found_and_creates_no_one()
    {
        $this->assertFalse(Route::has('register') || Route::has('register.store'), 'A closed door mounts no registration route.');
        $this->get('/register')->assertNotFound();
        $post = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'passwordConfirmation' => 'password',
        ]);
        // 405, not 404, where a GET-only catch-all ({path}) shares the path: either way no POST is routed (as the
        // flagship's RegistrationClosedTest).
        $this->assertContains($post->status(), [404, 405]);

        $this->assertFalse(User::query()->where('email', 'test@example.com')->exists());
        $this->assertGuest();
    }
}
