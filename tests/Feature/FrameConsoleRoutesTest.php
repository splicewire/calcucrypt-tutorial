<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\WithEnvironmentVariable;
use Schemastud\DataSchemas\Contracts\ServedSchemaRegistry;
use Schemastud\DataSchemas\Lifecycle\FilesystemSchemaRegistry;
use Splicewire\Beam\Ux\Frame\FrameNavContribution;
use Splicewire\Beam\Ux\Frame\RouteContextProjector;
use Tests\TestCase;

/**
 * The tenant frame nav is NAVIGABLE — every href the server projects is a mounted route that serves a
 * real page component.
 *
 * ⚠️ **The instrument here is deliberately the route table plus the rendered Inertia component, and
 * neither alone.** Before this suite existed the manifest was correct, the nav hrefs were correct, and
 * every one of them 404'd: `/frame/manifest` returning two populated seats cannot distinguish a wired
 * front end from an unwired one, which is exactly the failure mode this asserts against. Asking the
 * projector for the hrefs and then asking the HTTP kernel for each one is two differently-shaped
 * instruments; asserting the manifest twice would be one.
 *
 * The hrefs are read from {@see RouteContextProjector::hrefs()} rather than listed here on purpose: a
 * resource added to `config('frame.realms')['tenant']` must make this suite fail if `routes/web.php`
 * does not mount it.
 */
class FrameConsoleRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_projected_tenant_href_serves_the_frame_console(): void
    {
        $this->actingAs(User::factory()->create());

        $hrefs = app(RouteContextProjector::class)->hrefs('tenant');

        $this->assertNotEmpty($hrefs, 'The tenant realm must project hrefs, or this suite proves nothing.');

        foreach ($hrefs as $routeName => $href) {
            $url = str_replace(':id', '1', $href);

            $this->get($url)
                ->assertOk()
                ->assertInertia(fn (AssertableInertia $page) => $page->component('frame/console'));
        }
    }

    /**
     * The nav is read from `/frame/manifest` as a team member — the request the console itself makes.
     *
     * ⚠️ It used to call `FrameNavContribution::contributeNav()` straight from the test, after
     * `actingAs()` on a teamless user. Outside an HTTP request the injected `Request` has no user, so
     * that built the nav for a GUEST, and it passed only while one resource (`git-repo`) was listed to
     * everyone for lack of any read boundary. Once `ResourceVisibility::listable()` asked the read guard
     * (laravel-beam 88f872fd3) the guest's nav was empty, correctly. The rail offers only what its
     * viewer can read, so the viewer must be one who can: a member, whose team role `splicewire.team`
     * binds on the request.
     */
    public function test_every_nav_seat_href_serves_the_frame_console(): void
    {
        $this->actingAs(User::factory()->teamMember()->create());

        $tree = $this->getJson('/frame/manifest')->assertOk()->json('nav');
        $hrefs = [];

        foreach ($tree['items'] ?? [] as $section) {
            $hrefs[] = $section['href'] ?? null;

            foreach ($section['children'] ?? [] as $child) {
                $hrefs[] = $child['href'] ?? null;
            }
        }

        $hrefs = array_values(array_filter($hrefs));

        $this->assertNotEmpty($hrefs, 'The tenant nav must have seats, or this suite proves nothing.');

        foreach ($hrefs as $href) {
            $this->get($href)
                ->assertOk()
                ->assertInertia(fn (AssertableInertia $page) => $page->component('frame/console'));
        }
    }

    // The starter deliberately has no default authority. Declare this test's serving prerequisite
    // before providers boot; relying on a developer's .env leaves canonical installs with no door.
    #[WithEnvironmentVariable('SCHEMA_BASE_URI', 'https://starter.test/schemas')]
    public function test_the_schema_catalog_and_public_schema_document_both_serve_their_declared_content(): void
    {
        $directory = sys_get_temp_dir().'/beam-starter-schema-door-'.bin2hex(random_bytes(8));
        $document = [
            '$id' => 'https://starter.test/schemas/example/1',
            'type' => 'object',
            'properties' => ['title' => ['type' => 'string']],
        ];

        try {
            (new FilesystemSchemaRegistry($directory))->register($document);
            config(['data-schemas.served_directories' => [$directory]]);
            app()->forgetInstance(ServedSchemaRegistry::class);

            $this->get($document['$id'])
                ->assertOk()
                ->assertHeader('Content-Type', 'application/schema+json')
                ->assertExactJson($document);

            $this->actingAs(User::factory()->create());
            $hrefs = app(RouteContextProjector::class)->hrefs('tenant');
            $this->assertSame('/schema-catalog', $hrefs['schemas.index']);
            $this->assertSame('/schema-catalog/new', $hrefs['schemas.create']);
            $this->assertSame('/schema-catalog/:id', $hrefs['schemas.edit']);

            foreach (['schemas.index', 'schemas.create', 'schemas.edit'] as $routeName) {
                $this->get(str_replace(':id', '1', $hrefs[$routeName]))
                    ->assertOk()
                    ->assertInertia(fn (AssertableInertia $page) => $page->component('frame/console'));
            }

            $this->get('https://starter.test/schemas/missing/1')->assertNotFound();
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function test_a_guest_is_sent_to_login_rather_than_to_the_console(): void
    {
        $this->get('/beam-ux-entry')->assertRedirect(route('login'));

        foreach (['/schema-catalog', '/schema-catalog/new', '/schema-catalog/1'] as $path) {
            $this->get($path)->assertRedirect(route('login'));
        }
    }

    public function test_an_unclaimed_path_still_falls_through_to_the_public_entry_renderer(): void
    {
        // The console mount is a catch-all constrained to the projected segments. If that constraint
        // were dropped it would swallow `Route::beamUxSite()`'s `{path}` renderer, taking every
        // authored page with it — a failure the console's own routes could never show.
        $this->actingAs(User::factory()->create());

        $this->get('/definitely-not-a-frame-segment')->assertNotFound();
    }
}
