<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Rushing\PermissionCascade\Contracts\AccessGrant;
use Splicewire\Beam\Accounts\Enums\Role;
use Splicewire\Beam\Accounts\Models\Membership;
use Splicewire\Beam\Accounts\Models\Team;
use Splicewire\Beam\Accounts\Sharing\AccessGrants;
use Splicewire\Beam\Ux\Models\BeamUxEntry;
use Splicewire\Beam\Ux\Models\BeamUxRelease;
use Splicewire\Beam\Ux\Particle\EntryBodyEnvelope;
use Splicewire\Beam\Ux\Publish\EntryPublication;
use Splicewire\Beam\Ux\Release\ReleaseService;
use Splicewire\Beam\Ux\Seeders\ThemeSeeder;
use Tests\TestCase;

/** Run with the explicitly selected UX source overlay and published Release migrations. */
class ReleasePrerequisiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Storage::fake(config('beam.ux.storage.mirror_disk'));
        // This retained publication suite uses fake mirrors inside the checkout. Never let its
        // optional recorder discover or advance that checkout's real Git branch. Dedicated
        // recording tests supply explicitly owned disposable repositories instead.
        config(['beam.ux.recording.git' => PHP_BINARY.'-publication-test-no-git']);
    }

    private function author(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $team = Team::create(['user_id' => $user->id, 'name' => 'Release authors', 'personal_team' => false]);
        Membership::create(['team_id' => $team->id, 'user_id' => $user->id, 'role' => Role::Owner->value]);
        app(AccessGrants::class)->share(BeamUxEntry::rootFor('site'), $team, AccessGrant::ABILITY_MANAGE);

        return $user->fresh();
    }

    private function theme(): BeamUxEntry
    {
        $this->seed(ThemeSeeder::class);
        $entry = BeamUxEntry::where('namespace', 'theme')->where('slug', 'default')->sole();
        $publication = app(EntryPublication::class);
        // Legacy/fresh seeded entries receive the same explicit baseline for authoring.
        $publication->baseline($entry);

        return $entry->fresh();
    }

    private function draft(BeamUxEntry $entry, string $accent): string
    {
        $body = app(EntryBodyEnvelope::class)->read($entry)->body;
        $body['site']['accent'] = $accent;
        $publication = app(EntryPublication::class);

        return $publication->draftFromBase($entry, $body, $publication->state($entry)->headVersion)->headVersion;
    }

    public function test_http_publish_is_a_direct_release_and_save_draft_keeps_published_tokens(): void
    {
        $author = $this->author();
        $entry = $this->theme();
        $pin = $entry->published_version;
        $ref = $this->draft($entry, '#7c3aed');
        $this->assertSame($pin, $entry->fresh()->published_version);
        $response = $this->actingAs($author)->postJson("/beam-ux-entries/{$entry->id}/publish", ['ref' => $ref]);
        $response->assertSuccessful()->assertJsonPath('data.release.members.0.stale', false);
        $release = BeamUxRelease::sole();
        $this->assertSame('direct', $release->origin->value);
        $this->assertSame('published', $release->status);
        $this->assertSame($ref, $release->members()->sole()->version_id);
        $this->assertSame($ref, $entry->fresh()->published_version);
    }

    public function test_review_keeps_selected_version_despite_later_draft_and_denies_self_approval(): void
    {
        config(['beam.ux.releases.review_required' => true]);
        $author = $this->author();
        $reviewer = $this->author();
        $entry = $this->theme();
        $oldPin = $entry->published_version;
        $selected = $this->draft($entry, '#7c3aed');
        $service = app(ReleaseService::class);
        $release = $service->requestOne($entry->fresh(), $selected, $author);
        $this->assertSame('in_review', $release->status);
        $this->assertSame($oldPin, $entry->fresh()->published_version);
        $later = $this->draft($entry->fresh(), '#2563eb');
        $this->actingAs($author)->postJson("/beam-ux-releases/{$release->id}/command", ['action' => 'approve'])->assertForbidden();
        $this->actingAs($reviewer)->postJson("/beam-ux-releases/{$release->id}/command", ['action' => 'approve'])->assertSuccessful();
        $this->actingAs($reviewer)->postJson("/beam-ux-releases/{$release->id}/command", ['action' => 'publish'])->assertSuccessful();
        $this->assertSame($selected, $entry->fresh()->published_version);
        $this->assertSame($later, app(EntryPublication::class)->state($entry->fresh())->headVersion);
    }

    public function test_member_change_revokes_review_and_moved_pin_refuses_publication(): void
    {
        config(['beam.ux.releases.review_required' => true]);
        $author = $this->author();
        $reviewer = $this->author();
        $entry = $this->theme();
        $selected = $this->draft($entry, '#7c3aed');
        $release = app(ReleaseService::class)->requestOne($entry->fresh(), $selected, $author);
        $url = "/beam-ux-releases/{$release->id}/command";
        $this->actingAs($reviewer)->postJson($url, ['action' => 'approve'])->assertSuccessful();
        $this->actingAs($author)->postJson($url, ['action' => 'member', 'entryId' => $entry->id, 'ref' => $selected])->assertSuccessful();
        $this->assertNull($release->fresh()->reviewed_by);
        $this->assertSame('draft', $release->fresh()->status);
        $this->actingAs($author)->postJson($url, ['action' => 'submit'])->assertSuccessful();
        $this->actingAs($reviewer)->postJson($url, ['action' => 'approve'])->assertSuccessful();
        app(EntryPublication::class)->publishVersion($entry->fresh(), $selected);
        $this->actingAs($reviewer)->postJson($url, ['action' => 'publish'])->assertUnprocessable()->assertJsonValidationErrors('release');
    }

    public function test_guest_member_and_revoked_publisher_do_not_move_pins(): void
    {
        $author = $this->author();
        $entry = $this->theme();
        $ref = $this->draft($entry, '#7c3aed');
        $pin = $entry->published_version;
        $url = "/beam-ux-entries/{$entry->id}/publish";
        $this->postJson($url, ['ref' => $ref])->assertUnauthorized();
        $this->actingAs(User::factory()->create(['email_verified_at' => now()]))->postJson($url, ['ref' => $ref])->assertForbidden();
        config(['beam.ux.releases.publish_ability' => 'release-test.publish']);
        Gate::define('release-test.publish', fn () => false);
        $this->actingAs($author)->postJson($url, ['ref' => $ref])->assertForbidden();
        $this->assertSame($pin, $entry->fresh()->published_version);
        $this->assertSame(0, BeamUxRelease::count());
    }

    public function test_legacy_dashboard_fallback_preserves_host_denial_and_guest_refusal(): void
    {
        $ability = \Splicewire\Beam\Dashboard\RealmDashboard::OPEN_ABILITY;
        $this->assertFalse(Gate::allows($ability));
        Gate::define($ability, fn () => false);
        $method = new \ReflectionMethod(\Splicewire\Beam\Ux\BeamUxServiceProvider::class, 'defineOpenRealmAbility');
        $provider = new \Splicewire\Beam\Ux\BeamUxServiceProvider(app());
        $method->invoke($provider, 'tenant', app(\Splicewire\Beam\Realm\RealmRegistry::class));
        $this->assertFalse(Gate::forUser($this->author())->allows($ability));
        $this->assertSame('entitlement:os.operate', \Splicewire\Beam\Dashboard\RealmDashboard::abilityFor('operator', app(\Splicewire\Beam\Realm\RealmRegistry::class)));
    }
}
