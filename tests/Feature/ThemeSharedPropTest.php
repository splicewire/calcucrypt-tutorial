<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Splicewire\Beam\Models\BeamParticle;
use Splicewire\Beam\Ux\Models\BeamUxEntry;
use Splicewire\Beam\Ux\Seeders\ThemeSeeder;
use Splicewire\Beam\Ux\Theme\ThemeResolver;
use Tests\TestCase;

/**
 * theme-entries-and-authoring ticket `str-01`: `HandleInertiaRequests::theme()` shares the
 * `ThemeResolver` cascade as `page.props.theme`, degrading to schema defaults (never a 500) when no
 * central theme entry exists, and reflecting `ThemeSeeder`'s seeded values once one does.
 */
class ThemeSharedPropTest extends TestCase
{
    use RefreshDatabase;

    public function test_theme_shares_schema_defaults_when_no_central_entry_exists(): void
    {
        $response = $this->get(route('home'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('theme.canvas.accent')
            ->has('theme.shell.surface')
            ->has('theme.site.background')
        );
    }

    public function test_theme_reflects_the_seeded_central_entry(): void
    {
        $this->seed(ThemeSeeder::class);

        $entry = BeamUxEntry::query()->where('namespace', ThemeResolver::NAMESPACE)->where('slug', ThemeResolver::SLUG)->sole();
        $particle = BeamParticle::findOrFail($entry->particle_id);
        $this->assertSame('#0f172a', $particle->payload['canvas']['accent']);
        $resolver = app(ThemeResolver::class);
        $resolved = $resolver->resolve();
        $this->assertNull($resolver->lastFailure());
        $this->assertSame('#0f172a', $resolved['canvas']['accent']);

        $response = $this->get(route('home'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('theme.canvas.accent', '#0f172a')
            ->where('theme.shell.surface', '#0f172a')
            ->where('theme.site.background', '#f8fafc')
        );
    }

    public function test_theme_seeder_is_idempotent_no_orphaned_particles(): void
    {
        $this->seed(ThemeSeeder::class);
        $firstParticleId = BeamUxEntry::query()
            ->where('namespace', ThemeResolver::NAMESPACE)
            ->where('slug', ThemeResolver::SLUG)
            ->value('particle_id');

        $this->seed(ThemeSeeder::class);

        $entries = BeamUxEntry::query()
            ->where('namespace', ThemeResolver::NAMESPACE)
            ->where('slug', ThemeResolver::SLUG)
            ->get();

        $this->assertCount(1, $entries);
        // The SAME particle is reused across re-runs — not just one entry pointing at a fresh one
        // each time (which would still pass a count(1) on the entry alone, orphaning a particle
        // row per run).
        $this->assertSame($firstParticleId, $entries->first()->particle_id);
        $this->assertSame(1, BeamParticle::query()->count());
    }
}
