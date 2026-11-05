<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ServiceRequestJourneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_actor_creates_lists_and_reads_the_declared_request(): void
    {
        $actor = User::factory()->teamMember()->create();
        Sanctum::actingAs($actor);

        $created = $this->postJson('/api/service-requests', ['title' => 'Restore email delivery', 'status' => 'open'])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Restore email delivery')
            ->assertJsonPath('data.status', 'open');
        $id = $created->json('data.id');

        $this->getJson('/api/service-requests')->assertOk()->assertJsonPath('data.0.id', $id);
        $this->getJson('/api/service-requests/'.$id)->assertOk()->assertJsonPath('data.title', 'Restore email delivery');
        $this->assertDatabaseHas('service_requests', ['id' => $id, 'user_id' => $actor->id, 'status' => 'open']);
    }

    public function test_invalid_input_is_rejected_without_persisting_a_row(): void
    {
        Sanctum::actingAs(User::factory()->teamMember()->create());

        foreach ([[], ['title' => ''], ['title' => str_repeat('x', 201)], ['title' => 'Fix email', 'status' => 'pending']] as $body) {
            $this->postJson('/api/service-requests', $body)->assertUnprocessable();
            $this->assertDatabaseCount('service_requests', 0);
        }
    }

    public function test_guests_cannot_create_list_or_read_requests(): void
    {
        $this->postJson('/api/service-requests', ['title' => 'Fix email'])->assertUnauthorized();
        $this->getJson('/api/service-requests')->assertUnauthorized();
        $this->getJson('/api/service-requests/1')->assertUnauthorized();
        $this->assertDatabaseCount('service_requests', 0);
    }

    public function test_an_actor_on_another_team_cannot_read_or_forge_ownership(): void
    {
        $owner = User::factory()->teamMember()->create();
        $other = User::factory()->teamMember()->create();
        Sanctum::actingAs($owner);
        $id = $this->postJson('/api/service-requests', ['title' => 'Team request'])->assertCreated()->json('data.id');

        Sanctum::actingAs($other);
        $this->getJson('/api/service-requests')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/service-requests/'.$id)->assertNotFound();
        $forged = $this->postJson('/api/service-requests', [
            'title' => 'Ownership attempt', 'user_id' => $owner->id, 'userId' => $owner->id,
        ])->assertCreated()->json('data.id');
        $this->assertDatabaseHas('service_requests', ['id' => $forged, 'user_id' => $other->id]);
        $this->getJson('/api/service-requests')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $forged);

        Sanctum::actingAs($owner);
        $this->getJson('/api/service-requests/'.$forged)->assertNotFound();
        $this->getJson('/api/service-requests')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $id);
    }

    public function test_update_and_delete_are_not_exposed(): void
    {
        Sanctum::actingAs(User::factory()->teamMember()->create());
        $id = $this->postJson('/api/service-requests', ['title' => 'Read or create only'])->assertCreated()->json('data.id');
        $this->patchJson('/api/service-requests/'.$id, ['title' => 'Changed'])->assertMethodNotAllowed();
        $this->deleteJson('/api/service-requests/'.$id)->assertMethodNotAllowed();
        $this->assertDatabaseHas('service_requests', ['id' => $id, 'title' => 'Read or create only']);
    }
}
