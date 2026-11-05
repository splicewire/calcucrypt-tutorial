<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The camelCase wire (beam-accounts' `passwordConfirmation`, `currentPassword`) carries the same secrets the snake keys
 * did, so it gets the same protection: TrimStrings leaves the value byte-exact, and a validation failure never flashes
 * it back into the session's old input. The framework's snake defaults stay while any caller still sends them.
 */
class SecretInputsAreNeitherTrimmedNorFlashedTest extends TestCase
{
    private const SECRETS = [
        'password' => '  pass with spaces  ',
        'password_confirmation' => '  pass with spaces  ',
        'passwordConfirmation' => '  pass with spaces  ',
        'current_password' => '  current with spaces  ',
        'currentPassword' => '  current with spaces  ',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->post('/__secret-inputs/echo', fn (Request $request) => response()->json($request->all()));
        Route::middleware('web')->post('/__secret-inputs/invalid', function (Request $request) {
            $request->validate(['required_field' => 'required']);
        });
    }

    public function test_a_secret_is_not_trimmed_camel_or_snake(): void
    {
        $echo = $this->post('/__secret-inputs/echo', [...self::SECRETS, 'name' => '  trimmed  '])->assertOk();

        foreach (self::SECRETS as $key => $value) {
            $this->assertSame($value, $echo->json($key), $key);
        }
        $this->assertSame('trimmed', $echo->json('name'));
    }

    public function test_a_secret_is_never_flashed_into_old_input_camel_or_snake(): void
    {
        $this->from('/form')->post('/__secret-inputs/invalid', [...self::SECRETS, 'email' => 'person@example.test'])
            ->assertRedirect('/form')
            ->assertSessionHasErrors('required_field');

        $old = session()->getOldInput();
        foreach (array_keys(self::SECRETS) as $key) {
            $this->assertArrayNotHasKey($key, $old);
        }
        $this->assertSame('person@example.test', $old['email'] ?? null);
    }
}
