<?php

namespace Tests\Feature;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Splicewire\Beam\Ux\Access\EntryRequirement;
use Splicewire\Beam\Ux\Compile\EntryArtifactStore;
use Splicewire\Beam\Ux\Data\EntryPageSettingsData;
use Splicewire\Beam\Ux\Http\Middleware\ResolvePreviewContext;
use Splicewire\Beam\Ux\Models\BeamUxEntry;
use Splicewire\Beam\Ux\Models\BeamUxRelease;
use Splicewire\Beam\Ux\Preview\ReleasePreviewLinks;
use Splicewire\Beam\Ux\Publish\EntryPublication;
use Splicewire\Beam\Ux\Type\UxType;
use Tests\TestCase;

/**
 * ADR-0232 d15 (EP-18, ruling 8): `/` is this starter's own route, not the packaged renderer's, so a Preview there
 * showed the LIVE home with no bar. It now renders the Preview's version of the home body under the bar, and a home
 * the Preview takes down answers 404. Outside a Preview, `/` is unchanged (RoutePagePropsTest pins its props).
 */
class HomePreviewTest extends TestCase
{
    use RefreshDatabase;

    private BeamUxEntry $home;

    private string $published;

    protected function setUp(): void
    {
        parent::setUp();
        $this->home = BeamUxEntry::create(['slug' => 'home', 'type' => UxType::Page, 'format' => 'tsx', 'realm' => 'site',
            'namespace' => config('beam.ux.namespace', 'starter')]);
        $pub = app(EntryPublication::class);
        $this->published = $pub->recordDraft($this->home, [$this->node('Published home')])->headVersion;
        $pub->publishVersion($this->home->fresh(), $this->published);
    }

    public function test_the_front_door_renders_the_previews_home_under_the_bar(): void
    {
        // A requirement is not itself a denial: this one explicitly admits guests. Preview
        // metadata must use the same preview chain and guest-read decision as the page render.
        $this->home->forceFill(['requirements' => ['preview-guest-readable']])->save();
        app()->instance('beam.ux.requirement.preview-guest-readable', new class implements EntryRequirement
        {
            public function allows(?Authenticatable $actor, BeamUxEntry $entry): bool
            {
                return $actor === null;
            }
        });

        $draft = app(EntryPublication::class)->recordDraft($this->home->fresh(), [$this->node('Previewed home')],
            pageSettings: new EntryPageSettingsData(title: 'Previewed front door', description: 'What the Release says.'))->headVersion;
        $cookie = $this->openLink($this->release('Spring', $draft));
        $artifacts = app(EntryArtifactStore::class);

        // Outside a Preview first: a cookie set with withCookie() rides every later request in the test.
        $live = $this->withHeaders($this->inertia())->get('/');
        $live->assertOk();
        $this->assertSame($artifacts->version($this->home->fresh()), $live->json('props.entry.artifact.version'), 'outside a Preview, the live body');
        $this->assertArrayNotHasKey('preview', $live->json('props'));
        $this->assertStringNotContainsString('Previewed front door', implode('', $live->json('props.head')));

        $previewed = $this->withCookie(ResolvePreviewContext::COOKIE, $cookie)->withHeaders($this->inertia())->get('/');

        $previewed->assertOk();
        $this->assertSame($artifacts->versionKey($draft), $previewed->json('props.entry.artifact.version'), "the Release's body");
        $this->assertSame('Spring', $previewed->json('props.preview.label'));
        $this->assertSame('preview', $previewed->json('props.preview.mode'));
        $this->assertSame([$this->home->id], array_column($previewed->json('props.preview.changes.pages'), 'entryId'));
        $this->assertSame('no-store, private', $previewed->headers->get('Cache-Control'));
        // The head reads the Release's page settings too, as the packaged renderer's does.
        $this->assertStringContainsString('Previewed front door', $previewed->json('props.head.0'));
        $this->assertStringContainsString('What the Release says.', implode('', $previewed->json('props.head')));
        $this->assertStringNotContainsString('noindex', implode('', $previewed->json('props.head')),
            'a guest-readable requirement is not a reason to suppress the preview head');
    }

    public function test_a_home_the_preview_takes_down_answers_not_found_there_and_stays_live(): void
    {
        $cookie = $this->openLink($this->release('Take it down', null));

        $this->get('/')->assertOk();
        $this->withCookie(ResolvePreviewContext::COOKIE, $cookie)->get('/')->assertNotFound();
    }

    private function release(string $title, ?string $version): BeamUxRelease
    {
        $release = tap((new BeamUxRelease)->forceFill([
            'title' => $title, 'realm' => 'site', 'created_by' => 'user:author', 'edited_by' => 'user:author',
            'status' => 'draft', 'origin' => 'built', 'preview_links_enabled' => true, 'preview_link_secret' => ReleasePreviewLinks::newSecret(),
        ]))->save();
        $home = $this->home->fresh();
        DB::table('beam_ux_release_members')->insert([
            'id' => (string) Str::uuid(), 'release_id' => $release->id, 'entry_id' => $home->id, 'version_id' => $version,
            'observed_pin' => $home->published_version, 'observed_marking' => $home->workflow_marking,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $release;
    }

    /**
     * An Inertia visit's headers. It carries the asset version the starter's middleware computes: with the assets built,
     * Inertia answers a visit without it 409 (a version mismatch), not the page.
     *
     * @return array<string, string>
     */
    private function inertia(): array
    {
        $version = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(\Illuminate\Http\Request::create('/'));

        return ['X-Inertia' => 'true', 'X-Inertia-Version' => (string) $version];
    }

    /** Opens the Release's Preview Link as a guest and returns the Preview cookie's value. */
    private function openLink(BeamUxRelease $release): string
    {
        $response = $this->get((string) app(ReleasePreviewLinks::class)->url($release->fresh()))->assertRedirect();

        return (string) $response->getCookie(ResolvePreviewContext::COOKIE)?->getValue();
    }

    private function node(string $text): array
    {
        return ['kind' => 'block', 'name' => 'h1', 'isComponent' => false, 'dynamic' => false, 'props' => [],
            'children' => [['kind' => 'text', 'value' => $text]]];
    }
}
