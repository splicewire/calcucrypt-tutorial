<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceRequestSchemaPurposeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('t', 32))]);
        $this->withoutVite();
    }

    public function test_write_input_and_read_projection_have_distinct_declared_schemas(): void
    {
        $this->actingAs(User::factory()->teamMember()->create());
        $write = $this->getJson('/frame/resources/service-requests/schema')->assertOk()->json();
        $this->assertSame(['title', 'status'], array_keys($write['properties']));
        $this->assertSame($write, $this->getJson('/frame/resources/service-requests/schema?purpose=write')->assertOk()->json());
        $read = $this->getJson('/frame/resources/service-requests/schema?purpose=read')->assertOk()->json();
        $this->assertSame(['id', 'title', 'status', 'approvalState'], array_keys($read['properties']));
        $this->assertSame('Approval', $read['properties']['approvalState']['title']);
        $id = $this->postJson('/frame/resources/service-requests', ['title' => 'Read detail', 'status' => 'closed', 'approvalState' => 'approved', 'workflow_version' => 'forged'])
            ->assertOk()->assertJsonPath('data.approvalState', 'pending')->json('data.id');
        $this->getJson('/frame/resources/service-requests/records/'.$id)->assertOk()
            ->assertJsonPath('data.id', $id)->assertJsonPath('data.approvalState', 'pending')->assertJsonPath('data.status', 'closed');
        $this->assertDatabaseHas('service_requests', ['id' => $id, 'workflow_version' => null, 'approval_state' => 'pending']);
        $this->postJson('/frame/resources/service-requests', ['title' => 'Invalid', 'status' => 'approved'])->assertUnprocessable();
        $this->getJson('/frame/resources/service-requests/schema?purpose=other')->assertUnprocessable();
    }

    public function test_global_dto_metadata_does_not_disclose_foreign_record_values_or_operator_resources(): void
    {
        $owner = User::factory()->teamMember()->create();
        $outside = User::factory()->teamMember()->create();
        $this->actingAs($owner);
        $id = $this->postJson('/frame/resources/service-requests', ['title' => 'Foreign private value'])->assertOk()->json('data.id');
        $this->actingAs($outside);
        $this->getJson('/frame/resources/service-requests/schema?purpose=read')->assertOk()->assertDontSee('Foreign private value');
        $this->getJson('/frame/resources/service-requests/records/'.$id)->assertNotFound();
        $this->getJson('/frame/resources/teams/schema?purpose=other')->assertForbidden();
        $this->actingAs(User::factory()->create());
        $this->getJson('/frame/resources/service-requests/schema?purpose=read')->assertOk()->assertDontSee('Foreign private value');
        $this->getJson('/frame/resources/service-requests/records/'.$id)->assertForbidden();
    }

    public function test_guests_are_refused_before_purpose_validation(): void
    {
        $this->getJson('/frame/resources/service-requests/schema?purpose=read')->assertUnauthorized();
        $this->getJson('/frame/resources/service-requests/schema?purpose=other')->assertUnauthorized();
    }
}
