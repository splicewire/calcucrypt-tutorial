<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Rushing\PermissionCascade\Contracts\AccessGrant;
use Rushing\PermissionCascade\Contracts\EntitlementResolver;
use Splicewire\Beam\Accounts\Enums\Role;
use Splicewire\Beam\Accounts\Sharing\AccessGrants;
use Splicewire\Beam\Accounts\Teams\TeamProvisioner;
use Splicewire\Beam\Ux\Models\BeamUxEntry;
use Splicewire\Beam\Ux\Particle\EntryBodyEnvelope;
use Splicewire\Beam\Ux\Seeders\ThemeSeeder;
use Splicewire\Beam\Ux\Theme\ThemeResolver;
use Tests\TestCase;

class EntitlementGuideTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('e', 32))]);
        $this->withoutVite();
    }

    public function test_entitlement_aggregates_eligible_teams_whichever_team_is_selected(): void
    {
        $provisioner = app(TeamProvisioner::class);
        $ownerA = User::factory()->create();
        $teamA = $provisioner->createTeamFor($ownerA, 'A');
        $ownerB = User::factory()->create();
        $teamB = $provisioner->createTeamFor($ownerB, 'B');
        $actor = User::factory()->create();
        $provisioner->addMember($actor, $teamA, Role::Admin);
        $provisioner->addMember($actor, $teamB, Role::Member);
        $actor->switchTeam($teamB);
        $member = User::factory()->create();
        $provisioner->addMember($member, $teamA, Role::Member);
        $member->switchTeam($teamA);
        $root = BeamUxEntry::rootFor('site');
        $grants = app(AccessGrants::class);
        $this->assertFalse($actor->can('ux.author'));
        $grants->share($root, $teamA, AccessGrant::ABILITY_MANAGE);
        $this->assertTrue($actor->fresh()->can('ux.author'));
        $this->assertFalse($member->fresh()->can('ux.author'));
        $this->assertFalse($ownerB->fresh()->can('ux.author'));
        $this->assertNotContains('os.operate', app(EntitlementResolver::class)->entitlementsFor($actor->fresh()));
    }

    public function test_revoke_closes_the_existing_valid_shaped_write_without_body_or_version_changes(): void
    {
        $this->seed(ThemeSeeder::class);
        $owner = User::factory()->create();
        $team = app(TeamProvisioner::class)->createTeamFor($owner, 'Tutorial');
        $root = BeamUxEntry::rootFor('site');
        $grants = app(AccessGrants::class);
        $unrelated = $grants->share($root, $team, AccessGrant::ABILITY_VIEW);
        $theme = BeamUxEntry::query()->where('namespace', ThemeResolver::NAMESPACE)->where('slug', ThemeResolver::SLUG)->sole();
        $this->actingAs($owner->fresh())->get('/account/theme')->assertForbidden();
        $grants->share($root, $team, AccessGrant::ABILITY_MANAGE);
        $this->actingAs($owner->fresh())->get('/account/theme')->assertOk();
        $body = app(EntryBodyEnvelope::class)->read($theme)->body;
        $versions = DB::table('beam_versions')->orderBy('id')->get()->toJson();
        $grants->deny($root, $team, AccessGrant::ABILITY_MANAGE);
        $this->assertTrue($owner->fresh()->can('ux.author'), 'Installed default reads allow rows; deny is not subtraction here.');
        $grants->revoke($root, $team, AccessGrant::ABILITY_MANAGE);
        $this->assertTrue($unrelated->fresh()->exists);
        $this->actingAs($owner->fresh())->get('/account/theme')->assertForbidden();
        $this->getJson('/beam-ux-entries/'.$theme->id.'/body')->assertForbidden();
        $attempt = $body;
        $attempt['site']['accent'] = '#123456';
        $this->postJson('/beam-ux-entries/'.$theme->id.'/save-body', ['body' => $attempt])->assertForbidden();
        $this->assertSame($body, app(EntryBodyEnvelope::class)->read($theme->fresh())->body);
        $this->assertSame($versions, DB::table('beam_versions')->orderBy('id')->get()->toJson());
    }
}
