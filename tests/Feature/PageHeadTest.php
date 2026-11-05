<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Splicewire\Beam\Brand\Brand;
use Splicewire\Beam\Sitemap\Resolvers\SitemapBaseUrlResolver;
use Splicewire\Beam\Ux\Head\PageHeadComposer;
use Splicewire\Beam\Ux\Models\BeamUxEntry;
use Splicewire\Beam\Ux\Type\UxType;
use Tests\TestCase;

/**
 * ADR-0234 d1/d2 (EP-28): `/` passes the Page Head it composes from the home row, and the root template prints it in
 * the raw HTML, so a crawler or a link-preview bot that runs no script reads the page's own title and description
 * rather than the brand's name. A page with no head keeps that fallback title (ShellBrandTest).
 */
class PageHeadTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_front_door_prints_its_own_head_in_the_raw_html(): void
    {
        // Publish writes the SEO Fields onto the row itself; they are not mass-assignable.
        (new BeamUxEntry)->forceFill([
            'slug' => 'home', 'title' => 'Front door', 'type' => UxType::Page,
            'namespace' => config('beam.ux.namespace', 'starter'), 'seo_description' => 'What this site is for.',
        ])->save();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('<title data-inertia="title">'.e(PageHeadComposer::brandTitle('Front door', Brand::for())).'</title>', false);
        $response->assertSee('<meta data-inertia="description" name="description" content="What this site is for.">', false);
        $response->assertSee('<link data-inertia="canonical" rel="canonical" href="'.rtrim(app(SitemapBaseUrlResolver::class)->baseUrl(), '/').'/">', false);
        $response->assertDontSee('<title>'.e(Brand::for()->name).'</title>', false);
        // ADR-0234 d9: the starter installs laravel-data-schema-org (below), so its pages carry their Structured Data.
        $response->assertSee('<script data-inertia="structured-data" type="application/ld+json">', false);
    }

    public function test_the_starter_requires_the_schema_org_package_its_structured_data_needs(): void
    {
        // laravel-beam-ux only suggests it (GO 32, 2026-10-25: five live hosts don't install it), so a starter that
        // wants each page's @graph requires it itself, and every starter downstream inherits that line.
        $manifest = json_decode((string) file_get_contents(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertArrayHasKey('schemastud/laravel-data-schema-org', $manifest['require']);
    }
}
