<?php

namespace Tests\Feature;

use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Rushing\PermissionCascade\Contracts\AccessGrant;
use Splicewire\Beam\Accounts\Enums\Role;
use Splicewire\Beam\Accounts\Sharing\AccessGrants;
use Splicewire\Beam\Accounts\Teams\TeamProvisioner;
use Splicewire\Beam\Ux\Models\BeamUxEntry;
use Tests\TestCase;

class OperatorInspectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_operator_reads_outside_team_then_loses_and_regains_access_only_with_its_manage_grant(): void
    {
        $provisioner = app(TeamProvisioner::class);
        $operator = User::factory()->create();
        $team = $provisioner->createTeamFor($operator, 'Operators');
        $member = User::factory()->create();
        $provisioner->addMember($member, $team, Role::Member);
        $outside = User::factory()->create();
        $outsideTeam = $provisioner->createTeamFor($outside, 'Outside');
        $root = BeamUxEntry::rootFor('operator');
        $grants = app(AccessGrants::class);
        $view = $grants->share($root, $team, AccessGrant::ABILITY_VIEW);
        $paths = ['/operator/teams', '/operator/frame/manifest', '/frame/resources/teams',
            '/frame/resources/teams/records/'.$outsideTeam->id, '/frame/resources/teams/schema'];
        foreach ($paths as $path) {
            $this->actingAs($operator->fresh())->getJson($path)->assertForbidden();
        }
        $grants->share($root, $team, AccessGrant::ABILITY_MANAGE);
        $snapshot = fn () => collect(['users', 'beam_teams', 'beam_memberships', 'beam_access_grants', 'service_requests'])
            ->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all();
        $before = $snapshot();
        $this->actingAs($operator->fresh())->getJson('/frame/resources/teams/records/'.$outsideTeam->id)
            ->assertOk()->assertJsonPath('data.name', 'Outside')->assertJsonPath('data.ownerEmail', $outside->email)
            ->assertJsonPath('data.memberCount', 1)->assertJsonPath('data.personal', false);
        $this->getJson('/frame/resources/teams')->assertOk()->assertJsonCount(2, 'data');
        $this->assertSame($before, $snapshot(), 'Operator GETs must preserve business rows.');
        foreach ($paths as $path) {
            $this->actingAs($member->fresh())->getJson($path)->assertForbidden();
        }
        $grants->revoke($root, $team, AccessGrant::ABILITY_MANAGE);
        foreach ($paths as $path) {
            $this->actingAs($operator->fresh())->getJson($path)->assertForbidden();
        }
        $this->assertNotNull($view->fresh());
        $grants->share($root, $team, AccessGrant::ABILITY_MANAGE);
        $this->actingAs($operator->fresh())->getJson('/frame/resources/teams/records/'.$outsideTeam->id)
            ->assertOk()->assertJsonPath('data.ownerEmail', $outside->email);
    }

    public function test_operator_authority_does_not_bypass_selected_team_request_scope(): void
    {
        $provisioner = app(TeamProvisioner::class);
        $operator = User::factory()->create();
        $operatorTeam = $provisioner->createTeamFor($operator, 'Operators');
        $outside = User::factory()->create();
        $outsideTeam = $provisioner->createTeamFor($outside, 'Outside');
        app(AccessGrants::class)->share(BeamUxEntry::rootFor('operator'), $operatorTeam, AccessGrant::ABILITY_MANAGE);
        $request = new ServiceRequest(['title' => 'Outside queue', 'status' => 'open']);
        $request->user_id = $outside->id;
        $request->team_id = $outsideTeam->id;
        $request->save();
        $this->actingAs($operator->fresh())->getJson('/api/service-requests/'.$request->id)->assertNotFound();
        $this->getJson('/api/service-requests')->assertOk()->assertJsonCount(0, 'data');
    }
}
