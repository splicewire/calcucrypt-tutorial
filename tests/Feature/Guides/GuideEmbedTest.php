<?php

use App\Models\Guide;
use Database\Seeders\CryptSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Splicewire\Beam\Ux\Publish\EntryPublication;
use Tests\Support\FakeConnector;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('facts.connectors.coinmarketcap', FakeConnector::class);
    Illuminate\Support\Facades\Storage::fake('beam-ux');
    $this->withoutVite();
    $this->seed(CryptSeeder::class);
});

it('renders a guide with an embedded, server-resolved calculator', function (): void {
    $this->get('/guides/how-crypto-profit-works')->assertOk()->assertInertia(fn ($page) => $page
        ->component('guides/show')
        ->where('guide.slug', 'how-crypto-profit-works')
        ->has('embeds', 1)
        ->where('embeds.0.anchor', 'profit')
        ->where('embeds.0.spec.slug', 'crypto-profit')
        ->where('embeds.0.resolution.facts.sellPrice.source', 'FakeSource')
        ->has('links', 0));
});

it('surfaces a published link-mode association as a CTA, not an embed', function (): void {
    $entry = Guide::query()->where('slug', 'how-crypto-profit-works')->firstOrFail()->entry;
    $publication = app(EntryPublication::class);
    $body = $publication->publishedBodyOf($entry);
    $body['frontmatter']['calculators_json'] = '[{"slug":"crypto-profit","mode":"link","anchor":null}]';
    $state = $publication->recordDraft($entry, $body);
    $publication->publishVersion($entry->fresh(), $state->headVersion);

    $this->get('/guides/how-crypto-profit-works')->assertOk()->assertInertia(fn ($page) => $page
        ->has('embeds', 0)->has('links', 1)->where('links.0.slug', 'crypto-profit'));
});

it('404s an unpublished guide even though its published pin and index remain', function (): void {
    $guide = Guide::query()->where('slug', 'how-crypto-profit-works')->firstOrFail();
    $guide->entry->forceFill(['workflow_marking' => 'draft'])->save();
    expect($guide->entry->fresh()->published_version)->not->toBeNull();
    $this->get('/guides/how-crypto-profit-works')->assertNotFound();
});

it('lists published guides', function (): void {
    $this->get('/guides')->assertOk()->assertInertia(fn ($page) => $page->component('guides/index')->has('guides', 2));
});
