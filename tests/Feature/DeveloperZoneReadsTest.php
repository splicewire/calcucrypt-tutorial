<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\PermissionRegistrar;
use Splicewire\Beam\Accounts\Enums\Role;
use Splicewire\Beam\Accounts\Models\Team;
use Splicewire\Beam\Accounts\Teams\TeamProvisioner;
use Splicewire\Beam\Models\BeamSchema;
use Splicewire\Beam\Models\GitRepo;
use Splicewire\Beam\Schema\DatabaseSchemaRegistry;
use Splicewire\Beam\Ux\Diagnostics\DiagnosticsAbility;
use Tests\TestCase;

/**
 * ux-walkthrough UX-08c: a team member must not read the Developer zone's machinery: a host's repo roots and dirty paths
 * (git-repo), its schemas, its mirror and sitemap diagnostics. The team's owner and admin keep all of it. Two non-Root
 * roles throughout.
 */
class DeveloperZoneReadsTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'database/migrations/2026_10_05_220000_reserve_git_repo_and_beam_schema_tokens.php';

    /** @return array{0: Team, 1: User, 2: User, 3: User} */
    private function team(): array
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $team = app(TeamProvisioner::class)->personalTeamFor($owner);
        app(TeamProvisioner::class)->addMember($admin, $team, Role::Admin);
        app(TeamProvisioner::class)->addMember($member, $team, Role::Member);

        return [$team, $owner, $admin, $member];
    }

    private function asOf(User $user, Team $team): User
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($team->getKey());
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    /** @return list<string> the role's tokens, sorted */
    private function tokens(Team $team, string $role): array
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($team->getKey());
        $model = config('permission.models.role')::query()->where('team_id', $team->getKey())->where('name', $role)->firstOrFail();

        return $model->permissions()->pluck('name')->sort()->values()->all();
    }

    public function test_a_member_reads_none_of_the_developer_machinery_and_owner_and_admin_read_all_of_it(): void
    {
        [$team, $owner, $admin, $member] = $this->team();

        $member = $this->asOf($member, $team);
        $this->assertFalse(Gate::forUser($member)->allows('viewAny', GitRepo::class));
        $this->assertFalse(Gate::forUser($member)->allows('viewAny', BeamSchema::class));
        $this->assertFalse(Gate::forUser($member)->allows(DiagnosticsAbility::NAME));

        foreach ([$owner, $admin] as $editor) {
            $editor = $this->asOf($editor, $team);
            $this->assertTrue(Gate::forUser($editor)->allows('viewAny', GitRepo::class));
            $this->assertTrue(Gate::forUser($editor)->allows('viewAny', BeamSchema::class));
            $this->assertTrue(Gate::forUser($editor)->allows(DiagnosticsAbility::NAME));
        }
    }

    public function test_the_schemas_list_shows_the_owner_a_schema_and_the_member_nothing(): void
    {
        [$team, $owner, , $member] = $this->team();
        app(DatabaseSchemaRegistry::class)->register(['$id' => 'https://tower.test/schemas/probe/widget/1', 'type' => 'object']);

        $this->actingAs($this->asOf($owner, $team))->getJson('/frame/resources/schemas')->assertOk()->assertJsonPath('total', 1);

        // The member is refused outright: BeamSchemaPolicy reserves beam-schema.view, and the list read asks its viewAny
        // (launch security row 51a71469). It used to be served the list.
        $this->actingAs($this->asOf($member, $team))->getJson('/frame/resources/schemas')->assertForbidden();
    }

    public function test_the_migration_moves_only_the_two_families_and_is_reversible(): void
    {
        [$team] = $this->team();
        $owner = $this->tokens($team, 'owner');
        $admin = $this->tokens($team, 'admin');

        // The state a role row derived before the reservation holds, plus a hand-granted token on two roles.
        app(PermissionRegistrar::class)->setPermissionsTeamId($team->getKey());
        $memberRole = config('permission.models.role')::query()->where('team_id', $team->getKey())->where('name', 'member')->firstOrFail();
        $memberRole->givePermissionTo(config('permission.models.permission')::findOrCreate('git-repo.view', 'web'));
        $memberRole->givePermissionTo(config('permission.models.permission')::findOrCreate('beam-schema.view', 'web'));
        $memberRole->givePermissionTo(config('permission.models.permission')::findOrCreate('hand.granted', 'web'));
        config('permission.models.role')::query()->where('team_id', $team->getKey())->where('name', 'owner')->firstOrFail()
            ->givePermissionTo(config('permission.models.permission')::findOrCreate('hand.granted', 'web'));
        $memberBefore = $this->tokens($team, 'member');

        $migration = require base_path(self::MIGRATION);
        $migration->up();

        $this->assertSame(array_values(array_diff($memberBefore, ['git-repo.view', 'beam-schema.view'])), $this->tokens($team, 'member'));
        $this->assertContains('hand.granted', $this->tokens($team, 'member'), 'a hand-granted token survives');
        $this->assertSame(collect([...$owner, 'hand.granted'])->sort()->values()->all(), $this->tokens($team, 'owner'));
        $this->assertSame($admin, $this->tokens($team, 'admin'));

        $migration->down();

        $this->assertSame($memberBefore, $this->tokens($team, 'member'), 'down() restores the member tiering');
    }

    /**
     * Only the tier roles the old derivation fed (RolePermissions::DEFAULT_ABILITIES) move (review-r1). A role this
     * starter never derived, such as an operator's own 'developer' role granted git-repo.view by hand, keeps its grants,
     * both ways.
     */
    public function test_the_migration_leaves_a_role_outside_the_tiers_alone(): void
    {
        [$team] = $this->team();
        app(PermissionRegistrar::class)->setPermissionsTeamId($team->getKey());
        $developer = config('permission.models.role')::create(['name' => 'developer', 'guard_name' => 'web', 'team_id' => $team->getKey()]);
        $developer->givePermissionTo(config('permission.models.permission')::findOrCreate('git-repo.view', 'web'));
        $developer->givePermissionTo(config('permission.models.permission')::findOrCreate('beam-schema.view', 'web'));

        $migration = require base_path(self::MIGRATION);
        $migration->up();
        $this->assertSame(['beam-schema.view', 'git-repo.view'], $this->tokens($team, 'developer'));

        $migration->down();
        $this->assertSame(['beam-schema.view', 'git-repo.view'], $this->tokens($team, 'developer'));
    }
}
