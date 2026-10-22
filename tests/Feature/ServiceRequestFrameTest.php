<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceRequestFrameTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('t', 32))]);
        $this->withoutVite();
    }

    public function test_the_manifest_and_form_expose_the_declared_create_list_and_detail(): void
    {
        $this->actingAs(User::factory()->teamMember()->create());
        $manifest = $this->getJson('/frame/manifest')->assertOk();
        $context = $manifest->json('contexts.service-requests');
        $this->assertTrue($context['can']['create']);
        $this->assertFalse($context['can']['update']);
        $this->assertFalse($context['can']['delete']);
        $this->assertSame('Title', $context['byNode']['title']['list-column']['label']);
        $this->assertSame('badge', $context['byNode']['status']['list-column']['widget']);
        $leaves = collect($manifest->json('routeContext'))->where('resource', 'service-requests')->values();
        $this->assertSame(['list', 'edit', 'detail'], $leaves->pluck('mounts')->all());
        $this->assertSame(['service-requests', 'service-requests/new', 'service-requests/:id'], $leaves->pluck('path')->all());
        $this->getJson('/frame/resources/service-requests/schema')->assertOk()
            ->assertJsonPath('properties.title.maxLength', 200)
            ->assertJsonPath('properties.status.enum', ['open', 'closed'])
            ->assertJsonPath('properties.status.default', 'open')
            ->assertJsonMissingPath('properties.id')->assertJsonMissingPath('properties.user_id')
            ->assertJsonMissingPath('properties.userId')->assertJsonMissingPath('properties.team_id')
            ->assertJsonMissingPath('properties.teamId');
        $this->get('/service-requests')->assertOk();
        $this->get('/service-requests/new')->assertOk();
        $this->get('/service-desk')->assertOk();
    }

    public function test_frame_preserves_team_and_creator_and_refuses_unsupported_writes(): void
    {
        $owner = User::factory()->teamMember()->create();
        $other = User::factory()->teamMember()->create();
        $this->actingAs($owner);
        $this->postJson('/frame/resources/service-requests', [])->assertUnprocessable();
        $this->assertDatabaseCount('service_requests', 0);
        $id = $this->postJson('/frame/resources/service-requests', ['title' => 'Team request', 'user_id' => $other->id])
            ->assertOk()->assertJsonPath('data.status', 'open')->json('data.id');
        $this->assertDatabaseHas('service_requests', ['id' => $id, 'user_id' => $owner->id]);
        $this->getJson('/frame/resources/service-requests')->assertOk()->assertJsonPath('data.0.id', $id);
        $this->getJson('/frame/resources/service-requests/records/'.$id)->assertOk()->assertJsonPath('data.title', 'Team request');
        $this->putJson('/frame/resources/service-requests/records/'.$id, ['title' => 'Changed'])->assertForbidden();
        $this->deleteJson('/frame/resources/service-requests/records/'.$id)->assertForbidden();
        $this->actingAs($other);
        $this->getJson('/frame/resources/service-requests')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/frame/resources/service-requests/records/'.$id)->assertNotFound();
        $this->assertDatabaseHas('service_requests', ['id' => $id, 'title' => 'Team request', 'user_id' => $owner->id]);
    }

    public function test_guests_meet_the_authentication_boundary(): void
    {
        $this->get('/service-requests')->assertRedirect('/login');
        $this->getJson('/frame/resources/service-requests')->assertUnauthorized();
        $this->postJson('/frame/resources/service-requests', ['title' => 'Guest'])->assertUnauthorized();
        $this->assertDatabaseCount('service_requests', 0);
    }
}
