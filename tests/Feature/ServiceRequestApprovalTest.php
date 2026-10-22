<?php

namespace Tests\Feature;

use App\Models\User;
use Splicewire\Beam\Accounts\Enums\Role;
use Splicewire\Beam\Accounts\Teams\TeamProvisioner;
use Tests\TestCase;

class ServiceRequestApprovalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->artisan('migrate:fresh')->assertExitCode(0);
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        $this->withoutVite();
        $this->seed(\Database\Seeders\ServiceRequestApprovalSeeder::class);
    }

    public function test_owner_approves_a_members_request_without_changing_its_status_or_creator(): void
    {
        $owner = User::factory()->create();
        $team = app(TeamProvisioner::class)->createTeamFor($owner, 'Approval team');
        $member = User::factory()->create();
        app(TeamProvisioner::class)->addMember($member, $team, Role::Member);
        $member->switchTeam($team);
        $id = $this->actingAs($member->fresh())->postJson('/api/service-requests', ['title' => 'Approval regression', 'status' => 'closed'])
            ->assertCreated()->json('data.id');
        $this->actingAs($owner->fresh())->postJson('/service-requests/'.$id.'/approve')->assertOk()
            ->assertJsonPath('data.applied', true)->assertJsonPath('data.projection.current', 'approved');
        $this->getJson('/api/service-requests/'.$id)->assertOk()->assertJsonPath('data.approvalState', 'approved')
            ->assertJsonPath('data.status', 'closed');
        $this->assertDatabaseHas('service_requests', ['id' => $id, 'user_id' => $member->id, 'team_id' => $team->id]);
    }

    /** @return array{\App\Models\User, \Splicewire\Beam\Accounts\Models\Team, \App\Models\ServiceRequest} */
    private function pending(): array
    {
        $owner = User::factory()->create();
        $team = app(TeamProvisioner::class)->createTeamFor($owner, 'Approval');
        $record = new \App\Models\ServiceRequest(['title' => 'Pending decision', 'status' => 'open']);
        $record->user_id = $owner->id;
        $record->team_id = $team->id;
        $record->save();

        return [$owner, $team, $record];
    }

    private function business(): array
    {
        return collect(['service_requests', 'workflow_transition_facts', 'workflow_definition_versions', 'activity_log'])
            ->mapWithKeys(fn ($table) => [$table => \Illuminate\Support\Facades\DB::table($table)->orderBy('id')->get()->toJson()])->all();
    }

    public function test_admin_is_allowed_but_member_outside_guest_and_stale_membership_do_not_mutate(): void
    {
        [$owner, $team, $record] = $this->pending();
        $member = User::factory()->create();
        app(TeamProvisioner::class)->addMember($member, $team, Role::Member);
        $member->switchTeam($team);
        $outside = User::factory()->teamMember()->create();
        $before = $this->business();
        $url = '/service-requests/'.$record->id.'/approve';
        $this->postJson($url)->assertUnauthorized();
        $this->actingAs($member)->postJson($url)->assertForbidden();
        $this->actingAs($outside)->postJson($url)->assertNotFound();
        $removed = User::factory()->create();
        app(TeamProvisioner::class)->addMember($removed, $team, Role::Admin);
        $removed->switchTeam($team);
        $removed->load('currentTeam');
        $team->removeMember($removed);
        $this->actingAs($removed)->postJson($url)->assertNotFound();
        $dangling = User::factory()->create();
        $dangling->forceFill(['current_team_id' => 999999])->save();
        $this->actingAs($dangling)->postJson($url)->assertNotFound();
        $this->assertSame($before, $this->business());
        $admin = User::factory()->create();
        app(TeamProvisioner::class)->addMember($admin, $team, Role::Admin);
        $admin->switchTeam($team);
        $this->actingAs($admin)->postJson($url)->assertOk()->assertJsonPath('data.applied', true);
        $fact = app(\Splicewire\Beam\Workflows\Control\WorkflowHistory::class)->forSubject($record)->sole();
        $this->assertSame('user:'.$admin->id, $fact->actor);
        $this->assertSame(['pending'], $fact->from);
        $this->assertSame(['approved'], $fact->to);
        $this->assertSame($record->fresh()->workflow_version, $fact->version_id);
    }

    public function test_input_and_repeated_or_wrong_state_approval_are_honest_nonmutating_refusals(): void
    {
        [$owner, $team, $record] = $this->pending();
        $url = '/service-requests/'.$record->id.'/approve';
        $this->actingAs($owner);
        $before = $this->business();
        $this->postJson($url, ['actor' => 'user:forged', 'approval_state' => 'approved', 'workflow_version' => 'forged'])
            ->assertUnprocessable();
        $this->assertSame($before, $this->business());
        $this->postJson($url)->assertOk()->assertJsonPath('data.applied', true);
        $after = $this->business();
        $this->postJson($url)->assertConflict()->assertJsonPath('success', false)->assertJsonPath('data.applied', false);
        $this->assertSame($after, $this->business());
        $record->forceFill(['approval_state' => 'unknown'])->save();
        $wrong = $this->business();
        $this->postJson($url)->assertConflict()->assertJsonPath('data.applied', false);
        $this->assertSame($wrong, $this->business());
    }

    public function test_create_contract_ignores_approval_authority_fields_and_broken_version_pin_fails_closed(): void
    {
        [$owner, $team, $record] = $this->pending();
        $this->actingAs($owner);
        $id = $this->postJson('/api/service-requests', ['title' => 'Closed still valid', 'status' => 'closed',
            'approval_state' => 'approved', 'approvalState' => 'approved', 'workflow_version' => 'forged'])
            ->assertCreated()->assertJsonPath('data.status', 'closed')->assertJsonPath('data.approvalState', 'pending')->json('data.id');
        $this->assertNull(\App\Models\ServiceRequest::findOrFail($id)->workflow_version);
        $this->postJson('/api/service-requests', ['title' => 'Invalid status', 'status' => 'approved'])->assertUnprocessable();
        $record->forceFill(['workflow_version' => '00000000-0000-4000-8000-000000000001'])->save();
        $before = $this->business();
        $this->postJson('/service-requests/'.$record->id.'/approve')->assertConflict()->assertJsonPath('data.applied', false);
        $this->assertSame($before, $this->business());
    }

    public function test_outer_rollback_has_no_marking_pin_fact_or_effect_then_commit_emits_once(): void
    {
        [$owner, $team, $record] = $this->pending();
        $seen = [];
        app(\Splicewire\Beam\Workflows\Control\TransitionEffectRegistry::class)->register(
            \App\Workflows\ServiceRequestApproval::EFFECT,
            function ($event) use (&$seen): void {
                $seen[] = $event->transitionId;
            },
        );
        $before = $this->business();
        $connection = $record->getConnection();
        $connection->beginTransaction();
        $result = app(\Splicewire\Beam\Workflows\Control\WorkflowActuator::class)->transition($record, 'approve');
        $this->assertTrue($result->applied);
        $this->assertSame([], $seen);
        $connection->rollBack();
        $this->assertSame($before, $this->business());
        $this->assertSame([], $seen);
        $connection->transaction(function () use ($record, &$seen): void {
            $result = app(\Splicewire\Beam\Workflows\Control\WorkflowActuator::class)->transition($record->fresh(), 'approve');
            $this->assertTrue($result->applied);
            $this->assertSame([], $seen);
        });
        $this->assertCount(1, $seen);
        $this->assertSame('approved', $record->fresh()->approval_state);
        $this->assertDatabaseCount('workflow_transition_facts', 1);
    }

    public function test_failed_local_effect_keeps_committed_state_fact_and_version(): void
    {
        [$owner, $team, $record] = $this->pending();
        app(\Splicewire\Beam\Workflows\Control\TransitionEffectRegistry::class)->register(
            \App\Workflows\ServiceRequestApproval::EFFECT,
            function (): void {
                throw new \RuntimeException('Controlled local approval receipt failure');
            },
        );
        $this->actingAs($owner)->postJson('/service-requests/'.$record->id.'/approve')->assertOk()->assertJsonPath('data.applied', true);
        $this->assertSame('approved', $record->fresh()->approval_state);
        $this->assertNotNull($record->fresh()->workflow_version);
        $this->assertDatabaseCount('workflow_transition_facts', 1);
    }

    public function test_unrelated_workflow_programming_errors_remain_server_failures(): void
    {
        [$owner, $team, $record] = $this->pending();
        $actuator = \Mockery::mock(\Splicewire\Beam\Workflows\Control\WorkflowActuator::class);
        $actuator->shouldReceive('transition')->once()->andThrow(new \Symfony\Component\Workflow\Exception\LogicException('Unexpected programming failure'));
        $this->app->instance(\Splicewire\Beam\Workflows\Control\WorkflowActuator::class, $actuator);
        $before = $this->business();
        $this->actingAs($owner)->postJson('/service-requests/'.$record->id.'/approve')->assertStatus(500);
        $this->assertSame($before, $this->business());
    }
}
