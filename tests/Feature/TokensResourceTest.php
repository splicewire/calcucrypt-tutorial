<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Splicewire\Beam\Accounts\Facades\BeamAccounts;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('serves the tokens resource with only the authenticated callers metadata', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $model = BeamAccounts::tokenModel();
    foreach ([[$owner, 'My token'], [$other, 'Someone else token']] as [$user, $name]) {
        $token = new $model;
        $token->forceFill([
            'tokenable_type' => $user->getMorphClass(),
            'tokenable_id' => $user->getKey(),
            'name' => $name,
            'token' => hash('sha256', $name),
            'abilities' => ['*'],
        ])->save();
    }

    $this->assertDatabaseCount('personal_access_tokens', 2);
    $this->actingAs($owner)->get('/frame/resources/tokens')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonPath('data.0.name', 'My token');
    $response = $this->actingAs($owner)->getJson('/frame/resources/tokens')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'My token');

    expect($response->getContent())->not->toContain('Someone else token', hash('sha256', 'My token'));
    $response->assertJsonMissingPath('data.0.token');

    $this->actingAs($other)->getJson('/frame/resources/tokens')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Someone else token');
});

it('requires authentication before returning token data', function () {
    $this->get('/frame/resources/tokens')->assertRedirect(route('login'));
    $this->getJson('/frame/resources/tokens')->assertUnauthorized();
});
