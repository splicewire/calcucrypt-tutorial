<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Rushing\PermissionCascade\Contracts\AccessGrant;
use Splicewire\Beam\Accounts\Enums\Role;
use Splicewire\Beam\Accounts\Models\Membership;
use Splicewire\Beam\Accounts\Models\Team;
use Splicewire\Beam\Accounts\Sharing\AccessGrants;
use Splicewire\Beam\Ux\Models\BeamUxEntry;
use Splicewire\Beam\Ux\Support\PageEntryRef;
use Splicewire\Beam\Ux\Type\UxType;
use Tests\TestCase;

/**
 * The entry-body transport, as this starter now mounts it: two id-addressed particle OPERATIONS on the
 * `beam-ux-entry` resource (ADR-0214 §1), replacing the slug-addressed `Route::beamUxEntries()` macro.
 *
 * beam-docs-satellite ticket 40 wrote this test because there was none — anywhere. The macro was retired
 * from two live hosts on the strength of an ADR clause that said the starters did not call it, and all
 * three called it on the same line of the same file. Four consecutive undercounts of this transport's
 * consumers happened with a green suite every time, because nothing in any repo asserted where the
 * transport was mounted or that anything reached it.
 */
class EntryBodyTransportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Saving a body writes through beam-ux's DISK MIRROR (`config/beam/ux.php` → the `beam-ux` disk,
     * rooted at `resources/beam-ux/`), which is a TRACKED directory in this repo. Without this the
     * round-trip test below leaves `resources/beam-ux/starter/page/round-trip.tsx` in the working tree
     * — a test that dirties the repo it runs in. Faking the disk redirects the mirror into a temp root.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('beam.ux.storage.mirror_disk', 'beam-ux'));
    }

    private function author(): User
    {
        $user = User::factory()->create();
        $team = Team::create(['user_id' => $user->id, 'name' => 'A Team', 'personal_team' => false]);
        Membership::create(['team_id' => $team->id, 'user_id' => $user->id, 'role' => Role::Owner->value]);
        app(AccessGrants::class)->share(BeamUxEntry::rootFor('site'), $team, AccessGrant::ABILITY_MANAGE);

        return $user->fresh();
    }

    private function pageEntry(string $slug): BeamUxEntry
    {
        return BeamUxEntry::create([
            'slug' => $slug,
            'title' => $slug,
            'type' => UxType::Page,
            'namespace' => config('beam.ux.namespace', 'starter'),
        ]);
    }

    /**
     * The retired macro leaves no trace. A route file calling a macro that is not registered throws at
     * BOOT, before any route is served — which is exactly what `laravel-satellite-starter`'s log
     * recorded on 2026-08-21 — so this is a boot assertion, not a routing one.
     */
    public function test_the_slug_addressed_macro_is_not_mounted(): void
    {
        $this->assertNull(Route::getRoutes()->getByName('beam.ux.entries.body.show'));
        $this->assertNull(Route::getRoutes()->getByName('beam.ux.entries.body.update'));
    }

    public function test_the_two_id_addressed_operations_are_mounted(): void
    {
        $show = Route::getRoutes()->getByName('beam-ux-entry.body');
        $save = Route::getRoutes()->getByName('beam-ux-entry.save-body');

        $this->assertNotNull($show);
        $this->assertNotNull($save);
        // The read mounts GET (ADR-0214 §4); `particleOp` defaults to POST regardless of kind.
        $this->assertContains('GET', $show->methods());
        $this->assertContains('POST', $save->methods());
    }

    public function test_an_author_round_trips_an_entry_body_by_id(): void
    {
        $entry = $this->pageEntry('round-trip');
        $doc = [['kind' => 'block', 'name' => 'p', 'isComponent' => false, 'dynamic' => false, 'props' => [], 'children' => []]];

        $this->actingAs($this->author())
            ->postJson(route('beam-ux-entry.save-body', ['id' => $entry->getKey()]), ['body' => $doc])
            ->assertSuccessful();

        $this->actingAs($this->author())
            ->getJson(route('beam-ux-entry.body', ['id' => $entry->getKey()]))
            ->assertSuccessful()
            ->assertJsonPath('data.body.0.name', 'p');
    }

    /**
     * The read declares `input: false`, so a GET carrying ANY query key is a 422. The retired
     * `?namespace=` disambiguator is not merely ignored — it fails loudly, which is the point of
     * addressing by id (ADR-0214 §2).
     */
    public function test_the_read_refuses_a_query_string(): void
    {
        $entry = $this->pageEntry('no-query');

        $this->actingAs($this->author())
            ->getJson(route('beam-ux-entry.body', ['id' => $entry->getKey()]).'?namespace=starter')
            ->assertStatus(422);
    }

    /**
     * The server is the only party that can bind a page to a row by id — no compile-time frontend map
     * can carry a per-database uuid. This is what replaced the Mainframe host's `componentSlugFallback`
     * guess at this starter.
     */
    public function test_the_home_page_shares_its_entry_ref_by_id(): void
    {
        $entry = $this->pageEntry('home');

        $this->assertSame(
            [
                'id' => (string) $entry->getKey(),
                'slug' => 'home',
                // The entry's BODY LANGUAGE: which editor may open it. Missing this is how the operator
                // dock opened the JsonDoc canvas on an mdx entry and one Save blanked the public page.
                'format' => 'tsx',
                // Null until something is compiled — "never authored", not "the artifact failed".
                'artifact' => null,
            ],
            PageEntryRef::for('home'),
        );

        $this->get('/')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page->where('entry.id', (string) $entry->getKey()));
    }

    /**
     * G2-BEAM-AUTHOR-ENTRY, measured on beam.test 2026-09-11: the owner authored `/`, Save reported
     * "Saved" truthfully — the body reached the particle and the artifact compiled — and no reader ever
     * saw the change. `site/home` rendered its packaged default tree because the props carried a SAVE
     * address and nothing to read. This is the address that fixes it, and the version must be pinned
     * INTO the url: the browser caches by URL, and an unpinned one is how a body edit never reaches a
     * returning reader (ADR-0209 §7).
     */
    public function test_the_home_ref_carries_the_compiled_artifact_address_once_there_is_one(): void
    {
        $entry = $this->pageEntry('home');
        $artifacts = app(\Splicewire\Beam\Ux\Compile\EntryArtifactStore::class);
        $artifacts->put($entry, 'export default () => null');

        $ref = PageEntryRef::for('home');
        $version = $artifacts->version($entry);

        $this->assertSame($version, $ref['artifact']['version']);
        $this->assertStringContainsString((string) $entry->getKey(), $ref['artifact']['url']);
        $this->assertStringContainsString($version, $ref['artifact']['url']);

        $this->get('/')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page->where('entry.artifact.version', $version));
    }

    /**
     * EP-07d, starter receipt 2026-10-25: a form published on `/` read 'This form is not available.', because only the
     * packaged entry renderer put the forms' schemas on its page and `/` renders from this host's own route.
     */
    public function test_the_home_page_gives_its_published_forms_their_schemas_and_the_honeypot_field(): void
    {
        $entry = $this->pageEntry('home');
        $schema = ['type' => 'object', 'properties' => ['title' => ['type' => 'string']], 'required' => ['title']];
        $this->app->instance(\Splicewire\Beam\Schema\Contracts\SchemaTargetResolver::class, new class($schema) implements \Splicewire\Beam\Schema\Contracts\SchemaTargetResolver
        {
            public function __construct(private array $schema) {}

            public function targetFor(string $recordType, ?int $version = null): array
            {
                return $recordType === 'contact-request' ? $this->schema : [];
            }
        });
        $form = ['kind' => 'block', 'name' => 'IntakeForm', 'isComponent' => true, 'dynamic' => false, 'id' => '01a1f000-0000-7000-8000-000000000001',
            'props' => [['name' => 'captureKey', 'kind' => 'string', 'value' => 'contact-request']], 'children' => []];
        $this->app->instance(\Splicewire\Beam\Ux\Bindings\PublishedBindingDocument::class, new class([$form]) extends \Splicewire\Beam\Ux\Bindings\PublishedBindingDocument
        {
            public function __construct(private array $body) {}

            public function read(BeamUxEntry $entry): array
            {
                return $entry->slug === 'home' ? $this->body : [];
            }
        });
        config(['beam.core.intake.enabled' => true, 'beam.core.intake.public_schemas' => ['contact-request'], 'beam.core.intake.honeypot.field' => 'nickname']);

        $this->get('/')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page->where('entry.id', (string) $entry->getKey())
                ->where('intakeForms', ['contact-request' => $schema])
                ->where('intakeHoneypotField', 'nickname'));
    }

    public function test_a_page_entry_ref_is_null_when_the_row_was_never_seeded(): void
    {
        $this->assertNull(PageEntryRef::for('never-seeded'));
    }
}
