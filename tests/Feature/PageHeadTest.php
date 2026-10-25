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
    }
}
