<?php

namespace Tests\Feature;

use App\Beam\RealmRegistry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Rushing\PermissionCascade\Contracts\AccessGrant;
use Splicewire\Beam\Accounts\Enums\Role;
use Splicewire\Beam\Accounts\Models\Membership;
use Splicewire\Beam\Accounts\Models\Team;
use Splicewire\Beam\Accounts\Sharing\AccessGrants;
use Splicewire\Beam\Ux\Models\BeamUxEntry;
use Splicewire\Beam\Ux\Seeders\ThemeSeeder;
use Splicewire\Beam\Ux\Theme\ThemeResolver;
use Tests\TestCase;

/**
 * G2-BEAM-THEME-NAV — the theme entry's editor SEAT at this host (`/account/theme`).
 *
 * The acceptance row asks for a theme token to be editable "through the real UI". The mechanism for
 * that already existed end to end — the `body` / `save-body` operations, `EntryBodyEnvelope::schemaFor()`
 * answering a `UxType::Theme` entry with `ThemeSchemas`' JSON Schema, `ThemeResolver`'s cascade into
 * `page.props.theme`, and `<SiteLayout>`'s `--theme-site-*` block reading it. What did not exist was a
 * page that mounted any of it: the theme row has no `segment`, so nothing routed to it and the in-place
 * editor dock (which attaches to a RENDERED page) never had one to attach to.
 *
 * This pins the seat, and specifically the one thing only the server can supply: **the entry id**. A
 * theme row's id is a per-database uuid, so a frontend that hardcoded or guessed it would work on the
 * machine it was written on and 404 everywhere else — which is why the page is prop-addressed and why
 * the prop is asserted here against the row {@see ThemeSeeder} actually created.
 *
 * @see \Splicewire\Beam\Ux\Support\PageEntryRef::theme()
 */
class ThemeEditorSeatTest extends TestCase
{
    use RefreshDatabase;

    /** An author: an Owner of a Team holding `manage` on every realm root — the ACC-01 grant cascade. */
    private function author(): User
    {
        $user = User::factory()->create();
        $team = Team::create(['user_id' => $user->id, 'name' => 'A Team', 'personal_team' => false]);
        Membership::create(['team_id' => $team->id, 'user_id' => $user->id, 'role' => Role::Owner->value]);
        $grants = app(AccessGrants::class);

        foreach (RealmRegistry::realms() as $realm) {
            $grants->share(BeamUxEntry::rootFor($realm), $team, AccessGrant::ABILITY_MANAGE);
        }

        return $user->fresh();
    }

    public function test_an_author_is_served_the_theme_editor_addressed_at_the_seeded_theme_row(): void
    {
        $this->seed(ThemeSeeder::class);

        $entry = BeamUxEntry::query()
            ->where('namespace', ThemeResolver::NAMESPACE)
            ->where('slug', ThemeResolver::SLUG)
            ->sole();

        $response = $this->actingAs($this->author())->get(route('account.theme'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('account/theme')
            // The id the page loads and saves by. Asserted against the row, not merely as "present":
            // a page carrying SOME uuid would render a form that saves into the wrong entry.
            ->where('entry.id', (string) $entry->getKey())
            ->where('entry.slug', ThemeResolver::SLUG)
            // The theme body's language — the codec that mirrors a save to
            // `resources/beam-ux/theme/theme/default.css`.
            ->where('entry.format', 'css')
            // A theme is read out of the database by `ThemeResolver` on every request, never compiled
            // to an addressable artifact the way a page body is.
            ->where('entry.artifact', null)
        );
    }

    public function test_the_page_says_so_rather_than_rendering_a_form_that_saves_nowhere_when_unseeded(): void
    {
        // No ThemeSeeder: this database has no theme row at all. The route must still serve — the
        // editor's own "no theme entry yet" branch is the honest answer, and a 500 here would take
        // out an account-realm page on every fresh install before its first seed.
        $response = $this->actingAs($this->author())->get(route('account.theme'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('account/theme')
            ->where('entry', null)
        );
    }

    public function test_a_signed_in_member_without_ux_author_is_refused_at_the_door(): void
    {
        $member = User::factory()->create();

        $this->assertFalse($member->can('ux.author'));

        $this->actingAs($member)->get(route('account.theme'))->assertForbidden();
    }

    public function test_a_guest_never_reaches_the_seat(): void
    {
        $this->get(route('account.theme'))->assertRedirect(route('login'));
    }
}
