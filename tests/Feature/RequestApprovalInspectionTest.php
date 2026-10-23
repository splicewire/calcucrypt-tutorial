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

    /** @return array{User, \Splicewire\Beam\Accounts\Models\Team, ServiceRequest} */
    private function requestForOwner(string $title = 'Scoped request'): array
    {
        $owner = User::factory()->create();
        $team = app(TeamProvisioner::class)->createTeamFor($owner, $title);
        $row = new ServiceRequest(['title' => $title, 'status' => 'open']);
        $row->user_id = $owner->id;
        $row->team_id = $team->id;
        $row->save();

        return [$owner, $team, $row];
    }

    public function test_foreign_removed_teamless_and_guest_refuse_before_history_or_metadata(): void
    {
        [$owner, $team, $row] = $this->requestForOwner();
        [$foreign] = $this->requestForOwner('Other');
        $this->mock(WorkflowHistory::class)->shouldNotReceive('forSubject');
        $this->mock(\Splicewire\Beam\Workflows\Definition\DefinitionStore::class)->shouldNotReceive('onConnection');
        $this->actingAs($foreign)->getJson('/service-requests/'.$row->id.'/approval-history')->assertNotFound();
        $this->actingAs($foreign)->get('/service-requests/'.$row->id.'/inspection')->assertNotFound();
        $root = User::factory()->create();
        $roles = config('permission.models.role');
        $root->assignRole($roles::findOrCreate('Root', 'web'));
        $this->actingAs($root)->getJson('/service-requests/'.$row->id.'/approval-history')->assertNotFound();
        $this->actingAs($root)->get('/service-requests/'.$row->id.'/inspection')->assertForbidden();
        $member = User::factory()->create();
        app(TeamProvisioner::class)->addMember($member, $team, Role::Member);
        $member->switchTeam($team);
        $team->memberships()->where('user_id', $member->id)->delete();
        $this->actingAs($member)->getJson('/service-requests/'.$row->id.'/approval-history')->assertNotFound();
        $this->actingAs(User::factory()->create())->getJson('/service-requests/'.$row->id.'/approval-history')->assertNotFound();
        auth()->forgetGuards();
        $this->getJson('/service-requests/'.$row->id.'/approval-history')->assertUnauthorized();
    }

    public function test_request_id_is_the_only_selector_and_page_back_link_is_console(): void
    {
        [$owner, , $row] = $this->requestForOwner();
        $history = $this->mock(WorkflowHistory::class);
        $history->shouldNotReceive('forSubject');
        foreach (['subject_type', 'subject_id', 'connection', 'version', 'filter', 'class'] as $selector) {
            $this->actingAs($owner)->getJson('/service-requests/'.$row->id.'/approval-history?'.$selector.'=forged')->assertUnprocessable();
        }
        $this->actingAs($owner)->json('GET', '/service-requests/'.$row->id.'/approval-history', ['subject_id' => 999])->assertUnprocessable();
        $this->actingAs($owner)->get('/service-requests/'.$row->id.'/inspection')->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->component('service-requests/inspection', false)
                ->where('requestId', $row->id)
                ->where('backHref', route('frame.console', ['frameRoute' => 'service-requests/'.$row->id]))
                ->missing('title')->missing('facts'));
    }

    public function test_old_fact_metadata_does_not_follow_new_active_definition_and_reads_do_not_write(): void
    {
        [$owner, , $row] = $this->requestForOwner();
        $this->actingAs($owner)->postJson('/service-requests/'.$row->id.'/approve')->assertOk();
        $fact = app(WorkflowHistory::class)->forSubject($row)->sole();
        $old = app(\Splicewire\Beam\Workflows\Definition\DefinitionStore::class)->version($fact->version_id);
        // Controlled metadata fixture: a newer active version must not relabel this fact.
        \Illuminate\Support\Facades\DB::table('workflow_definition_versions')->where('id', $old->id)->update(['is_active' => false]);
        $new = $old->replicate();
        $new->id = (string) \Illuminate\Support\Str::uuid();
        $new->version = 2;
        $new->is_active = true;
        $new->save();
        $before = $this->businessSnapshot();
        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($owner)->getJson('/service-requests/'.$row->id.'/approval-history')->assertOk()
                ->assertJsonPath('data.facts.0.versionId', $old->id)
                ->assertJsonPath('data.facts.0.definitionVersion', 1)
                ->assertJsonPath('data.currentPin', $old->id);
        }
        $this->assertSame($before, $this->businessSnapshot());
    }

    public function test_null_missing_version_and_unsafe_actor_are_explicit_without_raw_context(): void
    {
        [$owner, , $row] = $this->requestForOwner();
        $this->actingAs($owner)->postJson('/service-requests/'.$row->id.'/approve')->assertOk();
        $fact = app(WorkflowHistory::class)->forSubject($row)->sole();
        // Deliberately corrupted metadata controls, never public success facts.
        $db = \Illuminate\Support\Facades\DB::table('workflow_transition_facts')->where('id', $fact->id);
        $db->update(['version_id' => null, 'actor' => '<script>secret@example.test</script>']);
        $response = $this->getJson('/service-requests/'.$row->id.'/approval-history')->assertOk()
            ->assertJsonPath('data.facts.0.actorReference', null)
            ->assertJsonPath('data.facts.0.definitionStatus', 'not-recorded')
            ->assertJsonPath('data.facts.0.versionId', null);
        $this->assertSame(['requestId', 'title', 'currentApproval', 'currentPin', 'facts'], array_keys($response->json('data')));
        $this->assertSame(['id', 'transition', 'from', 'to', 'actorReference', 'occurredAt', 'versionId', 'definitionStatus', 'definitionKey', 'definitionName', 'definitionVersion'], array_keys($response->json('data.facts.0')));
        $this->assertStringNotContainsString('secret@', $response->getContent());
        $this->assertMatchesRegularExpression('/Z$/', $response->json('data.facts.0.occurredAt'));
        $missing = (string) \Illuminate\Support\Str::uuid();
        $db->update(['version_id' => $missing]);
        $this->getJson('/service-requests/'.$row->id.'/approval-history')->assertOk()
            ->assertJsonPath('data.facts.0.versionId', $missing)
            ->assertJsonPath('data.facts.0.definitionStatus', 'unavailable')
            ->assertJsonPath('data.facts.0.definitionVersion', null);
    }

    public function test_fact_source_error_is_not_empty_history(): void
    {
        [$owner, , $row] = $this->requestForOwner();
        $before = $this->businessSnapshot();
        $this->mock(WorkflowHistory::class)->shouldReceive('forSubject')->once()->andThrow(new \RuntimeException('private source failure'));
        $this->actingAs($owner)->getJson('/service-requests/'.$row->id.'/approval-history')->assertStatus(500);
        $this->assertSame($before, $this->businessSnapshot());
    }

    public function test_definition_source_error_is_not_missing_historical_metadata(): void
    {
        [$owner, , $row] = $this->requestForOwner();
        $this->actingAs($owner)->postJson('/service-requests/'.$row->id.'/approve')->assertOk();
        $before = $this->businessSnapshot();
        $store = $this->mock(\Splicewire\Beam\Workflows\Definition\DefinitionStore::class);
        $store->shouldReceive('onConnection')->once()->with($row->getConnectionName())->andReturnSelf();
        $store->shouldReceive('version')->once()->andThrow(new \RuntimeException('private definition failure'));
        $this->getJson('/service-requests/'.$row->id.'/approval-history')->assertStatus(500);
        $this->assertSame($before, $this->businessSnapshot());
    }

    public function test_outer_rollback_has_no_fact_but_throwing_post_commit_observer_keeps_fact(): void
    {
        [$owner, , $row] = $this->requestForOwner();
        $db = $row->getConnection();
        $db->beginTransaction();
        app(\Splicewire\Beam\Workflows\Control\WorkflowActuator::class)->transition($row, 'approve');
        $db->rollBack();
        $this->assertCount(0, app(WorkflowHistory::class)->forSubject($row));
        $this->assertSame('pending', $row->fresh()->approval_state);
        $observed = 0;
        \Illuminate\Support\Facades\Event::listen(\Splicewire\Beam\Workflows\Control\Events\WorkflowTransitioned::class, function () use (&$observed): never {
            $observed++;
            throw new \RuntimeException('Observer delivery unavailable');
        });
        $this->actingAs($owner)->postJson('/service-requests/'.$row->id.'/approve')->assertOk();
        $this->getJson('/service-requests/'.$row->id.'/approval-history')->assertOk()->assertJsonCount(1, 'data.facts');
        $this->assertSame(1, $observed);
    }

    public function test_current_team_a_b_a_reads_only_actual_requests(): void
    {
        [$owner, $a, $first] = $this->requestForOwner('Team A');
        [, $b, $second] = $this->requestForOwner('Team B');
        $member = User::factory()->create();
        foreach ([$a, $b] as $team) {
            app(TeamProvisioner::class)->addMember($member, $team, Role::Member);
        }
        foreach ([[$a, $first, $second], [$b, $second, $first], [$a, $first, $second]] as [$team, $visible, $hidden]) {
            $member->switchTeam($team);
            $this->actingAs($member->fresh())->getJson('/service-requests/'.$visible->id.'/approval-history')->assertOk()->assertJsonPath('data.requestId', $visible->id);
            $this->getJson('/service-requests/'.$hidden->id.'/approval-history')->assertNotFound();
        }
    }

    public function test_committed_schemas_declare_string_list_items_and_fact_collection(): void
    {
        $fact = json_decode(file_get_contents(resource_path('schemas/App/Data/RequestApprovalFactData.schema.json')), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(['type' => 'string'], $fact['properties']['from']['items']);
        $this->assertSame(['type' => 'string'], $fact['properties']['to']['items']);
        $output = json_decode(file_get_contents(resource_path('schemas/App/Data/ReadRequestApprovalHistoryOutputData.schema.json')), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('items', $output['properties']['facts']);
    }

    /** @return array<string, string> */
    private function businessSnapshot(): array
    {
        $db = \Illuminate\Support\Facades\DB::connection();
        $snapshot = [];
        foreach ($db->getSchemaBuilder()->getTableListing() as $table) {
            if (str_starts_with($table, 'sqlite_') || in_array($table, ['cache', 'cache_locks', 'sessions'], true)) {
                continue;
            }
            $snapshot[$table] = hash('sha256', json_encode($db->table($table)->get()->all(), JSON_THROW_ON_ERROR));
        }

        return $snapshot;
    }
}
