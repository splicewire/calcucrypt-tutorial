<?php

use App\Content\Guides\GuideImporter;
use App\Models\Guide;
use Database\Seeders\CryptSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Splicewire\Beam\Ux\Compile\EntryArtifactStore;
use Splicewire\Beam\Ux\Disk\RegisterEntriesFromDisk;
use Splicewire\Beam\Ux\Models\BeamUxEntry;
use Splicewire\Beam\Ux\Models\BeamUxRelease;
use Splicewire\Beam\Ux\Publish\EntryPublication;
use Splicewire\Beam\Ux\Release\ReleaseService;
use Splicewire\Beam\Workflows\Control\WorkflowActuator;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Illuminate\Support\Facades\Storage::fake('beam-ux');
    $this->withoutVite();
});

it('retains the published associations until the edited UX body is published', function (): void {
    $this->seed(CryptSeeder::class);
    $guide = Guide::query()->where('slug', 'how-crypto-profit-works')->firstOrFail();
    $entry = $guide->entry;
    $publication = app(EntryPublication::class);
    $pin = $entry->published_version;
    $body = $publication->publishedBodyOf($entry);
    $body['frontmatter']['calculators_json'] = '[{"slug":"crypto-profit","mode":"link","anchor":null}]';
    $state = $publication->recordDraft($entry, $body);

    $this->get('/guides/'.$guide->slug)->assertOk()->assertInertia(fn ($page) => $page->has('embeds', 1)->has('links', 0));
    expect($entry->fresh()->published_version)->toBe($pin);
    $publication->publishVersion($entry->fresh(), $state->headVersion);
    $this->get('/guides/'.$guide->slug)->assertOk()->assertInertia(fn ($page) => $page->has('embeds', 0)->has('links', 1));
    expect($guide->fresh()->source_pin)->toBe($state->headVersion);
});

it('gates a pinned draft from particle reads, calculator associations and its artifact', function (): void {
    $this->seed(CryptSeeder::class);
    $guide = Guide::query()->where('slug', 'how-crypto-profit-works')->firstOrFail();
    $entry = $guide->entry;
    $artifact = route('beam.ux.site.artifact', ['entry' => $entry->id, 'version' => app(EntryArtifactStore::class)->version($entry)]);
    $entry->forceFill(['workflow_marking' => 'draft'])->save();
    expect(app(EntryPublication::class)->publishedBodyOf($entry))->not->toBeNull();
    $this->getJson('/api/guides/'.$guide->slug)->assertNotFound();
    $this->get('/guides/'.$guide->slug)->assertNotFound();
    $this->get($artifact)->assertNotFound();
    $this->get('/calculators/crypto-profit')->assertOk()->assertInertia(fn ($page) => $page->where('primaryGuide', null)->has('guides', 1));
    $result = app(WorkflowActuator::class)->transition($entry->fresh(), 'publish');
    expect($result->applied)->toBeTrue();
    $this->get('/guides/'.$guide->slug)->assertOk();
    $this->get($artifact)->assertOk();
    $before = $entry->fresh()->getAttributes();
    $result = app(WorkflowActuator::class)->transition($entry->fresh(), 'publish');
    expect($result->applied)->toBeFalse()->and($entry->fresh()->getAttributes())->toBe($before);
});

it('keeps title body search and category include after replacing Post storage', function (): void {
    $this->seed(CryptSeeder::class);
    $this->get('/guides?filter[q]=holdings')->assertOk()->assertInertia(fn ($page) => $page->has('guides', 1)->where('guides.0.slug', 'how-crypto-profit-works'));
    $this->getJson('/api/guides/how-crypto-profit-works?include=category')->assertOk()->assertJsonPath('data.category', 'Profit');
    $pin = Guide::query()->where('slug', 'how-crypto-profit-works')->firstOrFail()->source_pin;
    $this->artisan('content:sync')->assertSuccessful();
    expect(Guide::query()->where('slug', 'how-crypto-profit-works')->firstOrFail()->source_pin)->toBe($pin);
});

it('rejects unsupported metadata and associations before import', function (string $needle, string $replacement): void {
    $source = file_get_contents(resource_path('content/guides/how-crypto-profit-works.mdx'));
    expect(fn () => app(GuideImporter::class)->check(str_replace($needle, $replacement, $source), ['crypto-profit']))->toThrow(Illuminate\Validation\ValidationException::class);
    expect(BeamUxEntry::query()->where('namespace', 'guides')->count())->toBe(0);
})->with([
    'nested YAML' => ['calculators_json:', "calculators:\n  - slug:"],
    'broken JSON' => ['[{"slug"', '[{bad'],
    'unknown calculator' => ['"slug":"crypto-profit"', '"slug":"missing"'],
    'invalid mode' => ['"mode":"embed"', '"mode":"other"'],
    'missing anchor' => ['"anchor":"profit"', '"anchor":"missing"'],
    'duplicate metadata' => ['title:', "title: Duplicate\ntitle:"],
    'unsupported field' => ['title:', "unsupported: yes\ntitle:"],
    'broken fence' => ['---', '--'],
]);

it('rolls back all Guide writes when the package reports a keep or compile failure', function (): void {
    $this->mock(RegisterEntriesFromDisk::class, function ($mock): void {
        $mock->shouldReceive('scan')->once()->andReturnUsing(function (...$arguments): array {
            $result = app()->build(RegisterEntriesFromDisk::class)->scan(...$arguments);
            expect(BeamUxEntry::query()->where('namespace', 'guides')->count())->toBe(2);
            $result['failed']['guides/how-crypto-profit-works.mdx'] = 'frontmatter was not kept';

            return $result;
        });
    });
    // This exercises the import's real database writes under its operator writer scope.
    // The mock changes only the package's reported outcome, after those writes.
    expect(fn () => $this->artisan('content:sync')->run())->toThrow(RuntimeException::class);
    expect(BeamUxEntry::query()->where('namespace', 'guides')->count())->toBe(0)->and(Guide::count())->toBe(0);
});

it('refuses scheduling without calendars and leaves the pinned draft hidden', function (): void {
    $this->seed(CryptSeeder::class);
    $guide = Guide::query()->where('slug', 'how-crypto-profit-works')->firstOrFail();
    $entry = $guide->entry;
    $entry->forceFill(['workflow_marking' => 'draft'])->save();
    $before = $entry->fresh()->getAttributes();
    app()->offsetUnset(Splicewire\Beam\Calendars\Actions\ActionService::class);
    expect(app()->bound(Splicewire\Beam\Calendars\Actions\ActionService::class))->toBeFalse();
    expect(fn () => app(ReleaseService::class)->schedule(new BeamUxRelease, now()->addDay()->toIso8601String(), 'UTC', App\Models\User::factory()->create()))->toThrow(Illuminate\Validation\ValidationException::class);
    expect($entry->fresh()->getAttributes())->toBe($before);
    $this->get('/guides/'.$guide->slug)->assertNotFound();
});

it('publishes calculator and Guide XML URLs while excluding a pinned draft Guide', function (): void {
    $this->seed(CryptSeeder::class);
    $source = app(Splicewire\Beam\Ux\Sitemap\EntrySitemapSource::class);
    $urls = collect($source->urls())->map(fn ($url) => $url->url)->all();
    expect($urls)->toContain(url('/guides/how-crypto-profit-works'));
    Guide::query()->where('slug', 'how-crypto-profit-works')->firstOrFail()->entry->forceFill(['workflow_marking' => 'draft'])->save();
    $urls = collect($source->urls())->map(fn ($url) => $url->url)->all();
    expect($urls)->not->toContain(url('/guides/how-crypto-profit-works'));
    $urls = collect(app(App\Content\Guides\CalculatorSitemapSource::class)->urls())->map(fn ($url) => $url->url)->all();
    expect($urls)->toContain(url('/calculators/crypto-profit'));
    expect($urls)->not->toContain(url('/calculators/crypto-capital-gains'));
});

it('imports a genuine draft with a pin without making its metadata publicly addressable', function (): void {
    $this->seed(CryptSeeder::class);
    $root = sys_get_temp_dir().'/tutorial-guide-'.bin2hex(random_bytes(8));
    mkdir($root.'/guides', 0700, true);
    $path = $root.'/guides/imported-draft.mdx';
    $source = str_replace(['workflow_marking: published', 'segment: /guides/how-crypto-profit-works'], ['workflow_marking: draft', 'segment: /guides/imported-draft'], file_get_contents(resource_path('content/guides/how-crypto-profit-works.mdx')));
    file_put_contents($path, $source);
    try {
        app(GuideImporter::class)->import($root);
        $entry = BeamUxEntry::query()->where('namespace', 'guides')->where('slug', 'imported-draft')->firstOrFail();
        expect($entry->workflow_marking)->toBe('draft')->and($entry->published_version)->not->toBeNull();
        expect(Guide::query()->where('entry_id', $entry->id)->exists())->toBeFalse();
        $this->getJson('/api/guides/imported-draft')->assertNotFound();
        $this->get('/guides/imported-draft')->assertNotFound();
        $this->get(route('beam.ux.site.artifact', ['entry' => $entry->id, 'version' => app(EntryArtifactStore::class)->version($entry)]))->assertNotFound();
        $this->get('/calculators/crypto-profit')->assertOk()->assertInertia(fn ($page) => $page->has('guides', 2));
    } finally {
        unlink($path);
        rmdir($root.'/guides');
        rmdir($root);
    }
});

it('refuses changed authored source without replacing the registered UX body', function (): void {
    $this->seed(CryptSeeder::class);
    $entry = Guide::query()->where('slug', 'how-crypto-profit-works')->firstOrFail()->entry;
    $pin = $entry->published_version;
    $root = sys_get_temp_dir().'/guide-conflict-'.bin2hex(random_bytes(6));
    mkdir($root.'/guides', recursive: true);
    $path = $root.'/guides/how-crypto-profit-works.mdx';
    file_put_contents($path, str_replace('The formula behind profit', 'Changed authored formula', file_get_contents(resource_path('content/guides/how-crypto-profit-works.mdx'))));
    try {
        expect(fn () => app(GuideImporter::class)->import($root))->toThrow(RuntimeException::class, 'how-crypto-profit-works.mdx: Authored source differs');
        expect($entry->fresh()->published_version)->toBe($pin);
        expect(app(EntryPublication::class)->publishedBodyOf($entry->fresh())['content'])->not->toContain('Changed authored formula');
    } finally {
        unlink($path);
        rmdir($root.'/guides');
        rmdir($root);
    }
});

it('preserves a UI draft when unchanged authored files are imported again', function (): void {
    $this->seed(CryptSeeder::class);
    $entry = Guide::query()->where('slug', 'how-crypto-profit-works')->firstOrFail()->entry;
    $publication = app(EntryPublication::class);
    $pin = $entry->published_version;
    $body = $publication->publishedBodyOf($entry);
    $body['content'] .= "\n\nUI author draft.\n";
    $draft = $publication->recordDraft($entry, $body);
    app(GuideImporter::class)->import();
    expect($entry->fresh()->published_version)->toBe($pin)
        ->and($entry->fresh()->particle->head_version)->toBe($draft->headVersion);
    expect($publication->publishedBodyOf($entry->fresh())['content'])->not->toContain('UI author draft.');
});

it('excludes a malformed published Guide without exposing its stale index or breaking other Guides', function (string $associations): void {
    $this->seed(CryptSeeder::class);
    $guide = Guide::query()->where('slug', 'how-crypto-profit-works')->firstOrFail();
    $entry = $guide->entry;
    $oldPin = $guide->source_pin;
    $publication = app(EntryPublication::class);
    $body = $publication->publishedBodyOf($entry);
    $body['frontmatter']['calculators_json'] = $associations;
    $draft = $publication->recordDraft($entry, $body);
    $publication->publishVersion($entry->fresh(), $draft->headVersion);
    $this->get('/guides')->assertOk()->assertInertia(fn ($page) => $page->has('guides', 1)->where('guides.0.slug', 'crypto-taxes-end-to-end'));
    $this->getJson('/api/guides/'.$guide->slug)->assertNotFound();
    $this->get('/guides/'.$guide->slug)->assertNotFound();
    $this->get('/guides/crypto-taxes-end-to-end')->assertOk();
    $this->get('/calculators/crypto-profit')->assertOk()->assertInertia(fn ($page) => $page->where('primaryGuide', null)->has('guides', 1));
    expect($guide->fresh()->source_pin)->toBe($oldPin);
})->with(['malformed JSON' => '{bad', 'unknown calculator' => '[{"slug":"missing-calculator","mode":"link","anchor":null}]']);

it('keeps the explicit Guide route ahead of the UX entry catchall', function (): void {
    $request = Illuminate\Http\Request::create('/guides/how-crypto-profit-works');
    expect(app('router')->getRoutes()->match($request)->getActionName())->toBe(App\Http\Controllers\GuideController::class.'@show');
});

it('keeps seeded non-Guide auth pages publicly available under the Page lifecycle', function (): void {
    $this->seed(Database\Seeders\AuthPagesSeeder::class);
    $entry = BeamUxEntry::query()->where('namespace', config('beam.ux.namespace'))->where('slug', 'login')->firstOrFail();
    expect(app(Splicewire\Beam\Ux\Sitemap\EntryPublishGate::class)->isPublished($entry))->toBeTrue();
    $this->get('/login')->assertOk()->assertInertia(fn ($page) => $page->component('auth/entry'));
});

it('reuses one catalog scan in a request and invalidates it when publication changes', function (): void {
    $this->seed(CryptSeeder::class);
    $catalog = app(App\Content\Guides\GuideCatalog::class);
    $catalog->invalidate();
    $scans = 0;
    Illuminate\Support\Facades\DB::listen(function ($query) use (&$scans): void {
        if (str_contains($query->sql, '"beam_ux_entries"') && str_contains($query->sql, '"namespace" = ?') && ! str_contains($query->sql, 'limit') && $query->bindings === ['guides']) {
            $scans++;
        }
    });
    expect($catalog->visibleEntryIds())->toHaveCount(2);
    expect($catalog->visibleEntryIds())->toHaveCount(2);
    expect($scans)->toBe(1);
    BeamUxEntry::query()->where('namespace', 'guides')->firstOrFail()->forceFill(['workflow_marking' => 'draft'])->save();
    expect($catalog->visibleEntryIds())->toHaveCount(1);
    expect($scans)->toBe(2);
});
