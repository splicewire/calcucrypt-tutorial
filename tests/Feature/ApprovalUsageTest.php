<?php

namespace Tests\Feature;

use App\Models\ServiceRequest;
use App\Models\User;
use App\Support\CommittedApprovals;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Splicewire\Beam\Accounts\Enums\Role;
use Splicewire\Beam\Accounts\Teams\TeamProvisioner;
use Splicewire\Beam\Workflows\Control\TransitionContext;
use Splicewire\Beam\Workflows\Control\WorkflowActuator;
use Tests\Concerns\WithholdsTables;
use Tests\TestCase;

class ApprovalUsageTest extends TestCase
{
    use RefreshDatabase;
    use WithholdsTables;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('u', 32))]);
        $this->withoutVite();
        $this->seed(\Database\Seeders\ServiceRequestApprovalSeeder::class);
    }

    private function owner(): User
    {
        $user = User::factory()->create();
        app(TeamProvisioner::class)->createTeamFor($user, 'Usage team');

        return $user;
    }

    private function pending(User $owner, string $status = 'open'): ServiceRequest
    {
        $row = new ServiceRequest(['title' => 'Count a committed approval', 'status' => $status]);
        $row->user_id = $owner->id;
        $row->team_id = $owner->current_team_id;
        $row->save();

        return $row;
    }

    private function quantity(int $expected): void
    {
        $this->getJson('/frame/resources/service-request-usage')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0', [
                'id' => 'approvals', 'meter' => 'service-request.approval', 'quantity' => $expected,
                'unit' => 'approvals', 'period' => 'lifetime',
            ]);
    }

    private function business(): array
    {
        return collect(['workflow_transition_facts', 'service_requests', 'activity_log'])
            ->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all();
    }

    public function test_mounted_zero_then_actual_approve_counts_and_repeat_does_not(): void
    {
        $owner = $this->owner();
        $member = User::factory()->create();
        app(TeamProvisioner::class)->addMember($member, $owner->currentTeam, Role::Member);
        $member->switchTeam($owner->currentTeam);
        $this->actingAs($member);
        $this->quantity(0);
        $id = $this->postJson('/api/service-requests', ['title' => 'Usage journey', 'status' => 'closed'])->assertCreated()->json('data.id');
        $this->postJson('/service-requests/'.$id.'/approve')->assertForbidden();
        $this->actingAs($owner);
        $this->postJson('/service-requests/'.$id.'/approve')->assertOk()->assertJsonPath('data.applied', true);
        $before = $this->business();
        $this->quantity(1);
        $this->getJson('/frame/resources/service-request-usage/records/approvals')->assertOk()->assertJsonPath('data.quantity', 1);
        // No total is reported: absent (beam's ArrayResponseEnvelope) or null (ResponseBodyEnvelope); json() reads both as null.
        $this->assertNull($this->getJson('/api/service-request-usage')->assertOk()->assertJsonPath('data.0.quantity', 1)->json('total'));
        $this->postJson('/service-requests/'.$id.'/approve')->assertConflict();
        $this->quantity(1);
        $this->assertSame($before, $this->business());
        $this->getJson('/frame/resources/service-request-usage/schema?purpose=read')->assertOk()->assertJsonPath('properties.quantity.type', 'integer')->assertJsonMissingPath('properties.quantity.default');
        $this->getJson('/frame/resources/service-request-usage/records/missing')->assertNotFound();
        $this->getJson('/api/service-request-usage/approvals')->assertNotFound();
    }

    public function test_only_exact_facts_from_current_team_retained_requests_count_each_fact(): void
    {
        $a = $this->owner();
        $b = $this->owner();
        $row = $this->pending($a);
        $other = $this->pending($b, 'closed');
        $actuator = app(WorkflowActuator::class);
        $actuator->transition($row, 'approve', new TransitionContext(actor: 'user:'.$a->id));
        $actuator->transition($other, 'approve', new TransitionContext(actor: 'user:'.$b->id));
        $fact = (array) DB::table('workflow_transition_facts')->where('subject_id', $row->id)->first();
        // Raw inserts are intentional source counterexamples in the isolated test DB, not product writes.
        $insert = function (array $changes = []) use ($fact): void {
            DB::table('workflow_transition_facts')->insert(array_replace($fact, ['id' => (string) Str::uuid()], $changes));
        };
        $insert(['actor' => 'another:actor', 'occurred_at' => '2001-01-01 00:00:00']);
        foreach ([['subject_type' => 'other'], ['transition' => 'reject'], ['from' => '["approved"]'], ['from' => '["pending","other"]'], ['to' => '["pending"]'], ['to' => '["approved","other"]'], ['subject_id' => '999999999'], ['subject_id' => 'not-a-number']] as $change) {
            $insert($change);
        }
        $quarantined = $this->pending($a);
        $quarantined->forceFill(['team_id' => null, 'approval_state' => 'approved'])->save();
        $insert(['subject_id' => (string) $quarantined->id]);
        $this->pending($a)->forceFill(['approval_state' => 'approved'])->save(); // marking without fact adds no usage
        $this->actingAs($a);
        $before = $this->business();
        $this->quantity(2); // one historical fact plus one additional eligible fact on the SAME subject
        $this->getJson('/frame/resources/service-request-usage?team='.$b->current_team_id.'&actor=forged&meter=other&period=today&quantity=999')->assertOk()->assertJsonPath('data.0.quantity', 2);
        $this->getJson('/api/service-request-usage?team='.$b->current_team_id.'&quantity=999')->assertOk()->assertJsonPath('data.0.quantity', 2);
        $this->getJson('/frame/resources/service-request-usage?filter[team]='.$b->current_team_id)->assertUnprocessable();
        $this->getJson('/api/service-request-usage?filter[period]=today')->assertUnprocessable();
        $this->assertSame($before, $this->business());
        $this->actingAs($b);
        $this->quantity(1);
    }

    public function test_authority_precedes_source_and_same_backing_rechecks_membership_and_selection(): void
    {
        $queries = 0;
        DB::listen(function ($query) use (&$queries): void {
            if (str_contains($query->sql, 'workflow_transition_facts')) {
                $queries++;
            }
        });
        foreach (['/frame/resources/service-request-usage', '/api/service-request-usage'] as $path) {
            $this->getJson($path)->assertUnauthorized();
        }
        $this->actingAs(User::factory()->create());
        foreach (['/frame/resources/service-request-usage', '/api/service-request-usage', '/frame/resources/service-request-usage/schema?purpose=read'] as $path) {
            $this->getJson($path)->assertForbidden();
        }
        $this->assertSame(0, $queries);
        $a = $this->owner();
        $b = $this->owner();
        app(WorkflowActuator::class)->transition($this->pending($a), 'approve');
        $original = $a->currentTeam;
        app(TeamProvisioner::class)->addMember($a, $b->currentTeam, Role::Member);
        $this->actingAs($a);
        request()->setUserResolver(fn () => $a);
        $backing = app(\App\Backings\ApprovalUsageBacking::class);
        $this->assertSame(1, $backing->records([], null, 2)->items()[0]->quantity);
        $a->switchTeam($b->currentTeam);
        $this->assertSame(0, $backing->records([], null, 2)->items()[0]->quantity);
        $a->switchTeam($original);
        $this->assertSame(1, $backing->records([], null, 2)->items()[0]->quantity);
        $a->load('currentTeam');
        $original->removeMember($a);
        $queries = 0;
        foreach (['/frame/resources/service-request-usage', '/api/service-request-usage', '/frame/resources/service-request-usage/schema?purpose=read', '/frame/resources/service-request-usage/records/approvals'] as $path) {
            $this->getJson($path)->assertForbidden();
        }
        $this->assertSame(0, $queries);
    }

    public function test_writes_and_rolled_back_transitions_do_not_add_usage(): void
    {
        $a = $this->owner();
        $row = $this->pending($a);
        $this->actingAs($a);
        $before = $this->business();
        $this->postJson('/frame/resources/service-request-usage', ['quantity' => 99])->assertForbidden();
        $this->putJson('/frame/resources/service-request-usage/records/approvals', ['quantity' => 99])->assertForbidden();
        $this->deleteJson('/frame/resources/service-request-usage/records/approvals')->assertForbidden();
        $this->postJson('/api/service-request-usage', ['quantity' => 99])->assertStatus(405);
        try {
            DB::transaction(function () use ($row): void {
                $this->assertTrue(app(WorkflowActuator::class)->transition($row, 'approve')->applied);
                throw new \RuntimeException('Rollback control');
            });
        } catch (\RuntimeException $error) {
            $this->assertSame('Rollback control', $error->getMessage());
        }
        $this->quantity(0);
        $this->assertSame('pending', $row->fresh()->approval_state);
        $this->assertSame($before, $this->business());
    }

    public function test_missing_fact_source_fails_list_and_detail_then_recovers_same_count(): void
    {
        $a = $this->owner();
        app(WorkflowActuator::class)->transition($this->pending($a), 'approve');
        $this->actingAs($a);
        $before = $this->business();
        // Each request meets the missing source on its own, and the table comes back on every driver (WithholdsTables).
        foreach ([
            fn () => $this->getJson('/frame/resources/service-request-usage')->assertStatus(500)->assertJsonMissingPath('data'),
            fn () => $this->getJson('/frame/resources/service-request-usage/records/approvals')->assertStatus(500)->assertJsonMissingPath('data'),
            fn () => $this->getJson('/api/service-request-usage')->assertStatus(500),
        ] as $request) {
            $this->withoutTable('workflow_transition_facts', $request);
        }
        $this->quantity(1);
        $this->assertSame($before, $this->business());
    }

    public function test_a_fact_counts_only_for_its_exact_request_id_on_every_driver(): void
    {
        $a = $this->owner();
        $row = $this->pending($a);
        app(WorkflowActuator::class)->transition($row, 'approve', new TransitionContext(actor: 'user:'.$a->id));
        $fact = (array) DB::table('workflow_transition_facts')->where('subject_id', (string) $row->id)->first();
        // `subject_id` is a string column and `service_requests.id` an integer. The join compares them as text, so a
        // numerically equal spelling names another subject. Before the cast, sqlite's affinity counted these and
        // Postgres refused the comparison (`character varying = bigint`). Raw inserts are intentional source
        // counterexamples in the isolated test DB, not product writes.
        foreach (['0'.$row->id, $row->id.'.0', ' '.$row->id] as $spelling) {
            DB::table('workflow_transition_facts')->insert(array_replace($fact, ['id' => (string) Str::uuid(), 'subject_id' => $spelling]));
        }
        $this->assertSame(1, app(CommittedApprovals::class)->forTeam((string) $a->current_team_id));
        $this->actingAs($a);
        $this->quantity(1);
    }
}
