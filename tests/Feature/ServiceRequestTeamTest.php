<?php

namespace Tests\Feature;

use App\Models\ServiceRequest;
use App\Models\User;
use App\Policies\ServiceRequestPolicy;
use App\Support\ServiceRequestTeam;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Splicewire\Beam\Accounts\Enums\Role;
use Splicewire\Beam\Accounts\Teams\TeamProvisioner;
use Tests\TestCase;

class ServiceRequestTeamTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_joined_member_reads_the_owners_request_through_both_transports(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = app(TeamProvisioner::class)->createTeamFor($owner, 'Team A');
        app(TeamProvisioner::class)->addMember($member, $team, Role::Member);
        $member->switchTeam($team);
        Sanctum::actingAs($owner->fresh());
        $id = $this->postJson('/api/service-requests', ['title' => 'Shared request'])->assertCreated()->json('data.id');
        Sanctum::actingAs($member->fresh());
        $this->getJson('/api/service-requests/'.$id)->assertOk()->assertJsonPath('data.title', 'Shared request');
        $this->actingAs($member->fresh());
        $this->getJson('/frame/resources/service-requests/records/'.$id)->assertOk()->assertJsonPath('data.title', 'Shared request');
    }

    public function test_invalid_selected_memberships_fail_closed_and_removal_revokes_the_next_request(): void
    {
        $owner = User::factory()->teamMember()->create();
        Sanctum::actingAs($owner);
        $id = $this->postJson('/api/service-requests', ['title' => 'Protected'])->assertCreated()->json('data.id');
        $team = ServiceRequestTeam::forUser($owner);
        $removed = User::factory()->create();
        app(TeamProvisioner::class)->addMember($removed, $team, Role::Member);
        $removed->switchTeam($team);
        $removed->load('currentTeam');
        Sanctum::actingAs($removed);
        $this->getJson('/api/service-requests/'.$id)->assertOk();
        $team->removeMember($removed);

        $foreign = User::factory()->teamMember()->create();
        $foreign->forceFill(['current_team_id' => $team->id])->save();
        $dangling = User::factory()->create();
        $dangling->forceFill(['current_team_id' => 999999])->save();
        foreach ([User::factory()->create(), $removed, $foreign, $dangling] as $actor) {
            $this->assertNull(ServiceRequestTeam::forUser($actor));
            Sanctum::actingAs($actor);
            $this->getJson('/api/service-requests')->assertForbidden();
            $this->postJson('/api/service-requests', ['title' => 'Forbidden'])->assertForbidden();
            $this->getJson('/api/service-requests/'.$id)->assertNotFound();
            $this->actingAs($actor);
            $this->getJson('/frame/resources/service-requests')->assertForbidden();
            $this->postJson('/frame/resources/service-requests', ['title' => 'Forbidden'])->assertForbidden();
            $this->getJson('/frame/resources/service-requests/records/'.$id)->assertForbidden();
        }
        $this->assertDatabaseCount('service_requests', 1);
        $this->assertSame($team->id, $removed->fresh()->current_team_id);
    }

    public function test_policy_uses_its_explicit_actor_instead_of_ambient_auth(): void
    {
        $allowed = User::factory()->teamMember()->create();
        $denied = User::factory()->create();
        $record = new ServiceRequest;
        $record->team_id = ServiceRequestTeam::forUser($allowed)->id;
        $policy = new ServiceRequestPolicy;
        $this->actingAs($allowed);
        $this->assertFalse($policy->viewAny($denied));
        $this->assertFalse($policy->create($denied));
        $this->assertFalse($policy->view($denied, $record));
        $this->actingAs($denied);
        $this->assertTrue($policy->viewAny($allowed));
        $this->assertTrue($policy->create($allowed));
        $this->assertTrue($policy->view($allowed, $record));
    }

    public function test_creator_and_team_are_server_assigned_and_sharing_is_bidirectional(): void
    {
        $owner = User::factory()->create();
        $team = app(TeamProvisioner::class)->createTeamFor($owner, 'A');
        $member = User::factory()->create();
        app(TeamProvisioner::class)->addMember($member, $team, Role::Member);
        $member->switchTeam($team);
        $outsider = User::factory()->teamMember()->create();
        $otherTeam = ServiceRequestTeam::forUser($outsider);
        foreach ([['/api/service-requests', 201], ['/frame/resources/service-requests', 200]] as [$path, $status]) {
            $this->actingAs($member);
            Sanctum::actingAs($member);
            $id = $this->postJson($path, [
                'title' => 'Member authored', 'user_id' => $outsider->id, 'userId' => $outsider->id,
                'team_id' => $otherTeam->id, 'teamId' => $otherTeam->id,
            ])->assertStatus($status)->json('data.id');
            $this->assertDatabaseHas('service_requests', ['id' => $id, 'team_id' => $team->id, 'user_id' => $member->id]);
            $this->actingAs($owner->fresh());
            Sanctum::actingAs($owner->fresh());
            $this->getJson('/api/service-requests/'.$id)->assertOk();
            $this->getJson('/frame/resources/service-requests/records/'.$id)->assertOk();
            $this->actingAs($outsider);
            Sanctum::actingAs($outsider);
            $this->getJson('/api/service-requests')->assertOk()->assertJsonCount(0, 'data');
            $this->getJson('/api/service-requests/'.$id)->assertNotFound();
            $this->getJson('/frame/resources/service-requests/records/'.$id)->assertNotFound();
        }
    }

    public function test_migration_preserves_private_rows_without_backfilling_and_scope_excludes_them(): void
    {
        $migration = require database_path('migrations/2026_10_21_191400_add_team_id_to_service_requests_table.php');
        $migration->down();
        $actor = User::factory()->teamMember()->create();
        $id = DB::table('service_requests')->insertGetId([
            'user_id' => $actor->id, 'title' => 'Historical private request', 'status' => 'closed',
            'created_at' => '2026-10-01 12:00:00', 'updated_at' => '2026-10-02 13:00:00',
        ]);
        $before = (array) DB::table('service_requests')->find($id);
        $migration->up();
        $after = (array) DB::table('service_requests')->find($id);
        $this->assertNull($after['team_id']);
        unset($after['team_id']);
        $this->assertSame($before, $after);
        Sanctum::actingAs($actor);
        $this->getJson('/api/service-requests')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/service-requests/'.$id)->assertNotFound();
        $this->actingAs($actor);
        $this->getJson('/frame/resources/service-requests')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/frame/resources/service-requests/records/'.$id)->assertNotFound();
    }

    public function test_a_team_with_requests_cannot_be_deleted(): void
    {
        $actor = User::factory()->teamMember()->create();
        Sanctum::actingAs($actor);
        $this->postJson('/api/service-requests', ['title' => 'Retained history'])->assertCreated();
        $this->expectException(QueryException::class);
        ServiceRequestTeam::forUser($actor)->delete();
    }
}
