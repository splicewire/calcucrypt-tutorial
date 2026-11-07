<?php

namespace Tests\Feature;

use App\Models\SitemapRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Splicewire\Beam\Accounts\Enums\Role;
use Splicewire\Beam\Accounts\Teams\TeamProvisioner;
use Tests\TestCase;

/**
 * The host half of the fail-closed write gate: `sitemap` is this starter's only fully write-capable
 * Frame resource, and {@see SitemapRecord} is the starter's own model, so the starter — not a
 * package — owes it a policy.
 *
 * `Schemastud\Frame\Authorization\ResourceAuthorizer` refuses a write against a model with no policy
 * for EVERY actor, owner included. Measured here 2026-09-05, before `SitemapRecord` carried
 * `#[UseCascadePolicy]`: `POST /frame/resources/sitemap` as `demo-owner@example.test` returned 403.
 * The nav editor the resource exists to serve was unusable by anyone.
 *
 * ⚠️ **The gate is CLOSED here.** Nothing in this file opens it, and the member denials are what
 * prove it ran — an authorization test taken with an open gate reports success by not asking.
 *
 * The round-trip assertions are not decoration. The very first write this host ever served exposed a
 * second, independent defect the closed gate had been hiding: `SitemapData` had no `prepare()`/
 * `fromModel()` pair, so a create answered 200 while persisting `payload = null`, then 500'd on the
 * read back. A test that only asserted the status code would have called that a pass.
 */
class SitemapWriteAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: User} owner and member of one team, with that team's scope bound */
    private function seats(): array
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $team = app(TeamProvisioner::class)->personalTeamFor($owner);
        app(TeamProvisioner::class)->addMember($member, $team, Role::Member);
        $member->switchTeam($team);

        // Every assertion below runs through an HTTP request, so `bootstrap/app.php`'s
        // `splicewire.team` middleware binds each actor's team-scoped roles from their active team
        // (`current_team_id`: set by `personalTeamFor` for the owner, by `switchTeam` for the member).
        // Clear any role/permission relations cached before that team context so the request reloads
        // them under the active team rather than a stale global scope.
        foreach ([$owner, $member] as $user) {
            $user->unsetRelation('roles')->unsetRelation('permissions');
        }

        return [$owner, $member];
    }

    public function test_the_sitemap_model_carries_a_policy_at_all(): void
    {
        // `ResourceAuthorizer::allows()` short-circuits false the moment this is null, before it asks
        // who is acting — so the non-null-ness is the assertion, not a formality.
        $this->assertNotNull(Gate::getPolicyFor(SitemapRecord::class));
    }

    public function test_an_owner_can_create_update_and_delete_a_nav_record(): void
    {
        [$owner] = $this->seats();

        $created = $this->actingAs($owner)
            ->postJson('/frame/resources/sitemap', [
                'label' => 'Guides',
                'href' => '/docs/build',
                'order' => 3,
            ])
            ->assertOk()
            // The payload must survive the write. `label`/`href` are not columns — they live inside
            // `schema_records.payload` — so a resource without `SitemapData::prepare()` answers 200
            // here while storing nothing.
            ->assertJsonPath('data.label', 'Guides')
            ->assertJsonPath('data.href', '/docs/build');

        $id = $created->json('data.id');

        $this->assertSame('Guides', SitemapRecord::findOrFail($id)->payload['label']);

        $this->actingAs($owner)
            ->getJson("/frame/resources/sitemap/records/{$id}")
            ->assertOk()
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.label', 'Guides')
            ->assertJsonPath('data.href', '/docs/build');

        $this->actingAs($owner)
            ->putJson("/frame/resources/sitemap/records/{$id}", [
                'label' => 'Guides & Recipes',
                'href' => '/docs/build',
                'order' => 4,
            ])
            ->assertOk()
            ->assertJsonPath('data.label', 'Guides & Recipes');

        $this->actingAs($owner)
            ->getJson("/frame/resources/sitemap/records/{$id}")
            ->assertOk()
            ->assertJsonPath('data.label', 'Guides & Recipes')
            ->assertJsonPath('data.order', 4);

        $this->actingAs($owner)
            ->deleteJson("/frame/resources/sitemap/records/{$id}")
            ->assertNoContent();

        $this->assertNull(SitemapRecord::find($id));
    }

    public function test_a_member_is_refused_every_write_while_the_read_stays_open(): void
    {
        [$owner, $member] = $this->seats();

        $id = $this->actingAs($owner)
            ->postJson('/frame/resources/sitemap', ['label' => 'Guides', 'href' => '/docs/build'])
            ->assertOk()
            ->json('data.id');

        $this->actingAs($member)
            ->postJson('/frame/resources/sitemap', ['label' => 'Mine', 'href' => '/mine'])
            ->assertForbidden();

        $this->actingAs($member)
            ->putJson("/frame/resources/sitemap/records/{$id}", ['label' => 'Hijack', 'href' => '/x'])
            ->assertForbidden();

        $this->actingAs($member)
            ->deleteJson("/frame/resources/sitemap/records/{$id}")
            ->assertForbidden();

        // The asymmetry is the rule, not an oversight: a policy-less resource stays READABLE because
        // its index may be gated by a row scope, and only the write axis fails closed. A member who
        // could no longer see the nav would be a regression, not a fix.
        $this->actingAs($member)
            ->getJson('/frame/resources/sitemap')
            ->assertOk()
            ->assertJsonPath('data.0.label', 'Guides');

        $this->assertNotNull(SitemapRecord::find($id), 'A refused write must not have landed.');
    }
}
