<?php

namespace Tests\Feature;

use App\Models\ServiceRequest;
use App\Models\User;
use Splicewire\Beam\Accounts\Enums\Role;
use Splicewire\Beam\Accounts\Teams\TeamProvisioner;
use Splicewire\Beam\Workflows\Control\WorkflowHistory;
use Tests\TestCase;

class RequestApprovalInspectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->artisan('migrate:fresh')->assertExitCode(0);
        config(['app.key' => 'base64:'.base64_encode(str_repeat('i', 32))]);
        $this->withoutVite();
        $this->seed(\Database\Seeders\ServiceRequestApprovalSeeder::class);
    }

    public function test_member_reads_pending_then_exact_native_committed_fact(): void
    {
        $owner = User::factory()->create();
        $team = app(TeamProvisioner::class)->createTeamFor($owner, 'Inspection');
        $member = User::factory()->create();
        app(TeamProvisioner::class)->addMember($member, $team, Role::Member);
        $member->switchTeam($team);
        $row = new ServiceRequest(['title' => 'Member inspection', 'status' => 'open']);
        $row->user_id = $member->id;
        $row->team_id = $team->id;
        $row->save();
        $url = '/service-requests/'.$row->id.'/approval-history';
        $this->actingAs($member)->getJson($url)->assertOk()
            ->assertJsonPath('data.requestId', $row->id)->assertJsonPath('data.currentApproval', 'pending')
            ->assertJsonPath('data.currentPin', null)->assertJsonPath('data.facts', []);
        $this->actingAs($owner->fresh())->postJson('/service-requests/'.$row->id.'/approve')->assertOk();
        $fact = app(WorkflowHistory::class)->forSubject($row)->sole();
        $this->actingAs($member)->getJson($url)->assertOk()
            ->assertJsonPath('data.currentApproval', 'approved')->assertJsonPath('data.currentPin', $fact->version_id)
            ->assertJsonPath('data.facts.0.id', $fact->id)->assertJsonPath('data.facts.0.from', ['pending'])
            ->assertJsonPath('data.facts.0.to', ['approved'])->assertJsonPath('data.facts.0.actorReference', 'user:'.$owner->id)
            ->assertJsonPath('data.facts.0.versionId', $fact->version_id)->assertJsonPath('data.facts.0.definitionStatus', 'available')
            ->assertJsonPath('data.facts.0.definitionKey', 'service-request.approval')->assertJsonPath('data.facts.0.definitionVersion', 1);
        $this->actingAs($owner)->postJson('/service-requests/'.$row->id.'/approve')->assertConflict();
        $this->assertCount(1, app(WorkflowHistory::class)->forSubject($row));
    }
}
