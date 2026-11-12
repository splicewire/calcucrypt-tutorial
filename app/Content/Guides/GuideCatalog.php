<?php

namespace App\Content\Guides;

use App\Content\CategoryCatalog;
use App\Models\Calculator;
use App\Models\Category;
use App\Models\Guide;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Splicewire\Beam\Mdx\Frontmatter\FrontmatterParser;
use Splicewire\Beam\Mdx\Frontmatter\FrontmatterResolver;
use Splicewire\Beam\Mdx\Frontmatter\ParsedFrontmatter;
use Splicewire\Beam\Ux\Files\MdxPageFile;
use Splicewire\Beam\Ux\Http\PublicEntryGate;
use Splicewire\Beam\Ux\Models\BeamUxEntry;
use Splicewire\Beam\Ux\Publish\EntryPublication;

class GuideCatalog
{
    public const NAMESPACE = 'guides';

    private ?\Illuminate\Http\Request $request = null;

    /** @var array<string, list<string>> */
    private array $visible = [];

    /**
     * Gate before reading snapshots; a draft import can have a publication pin.
     *
     * @return list<string>
     */
    public function visibleEntryIds(): array
    {
        $request = request();
        if ($this->request !== $request) {
            $this->invalidate();
            $this->request = $request;
        }
        $key = (string) ($request->user()?->getAuthIdentifier() ?? 'guest');
        if (array_key_exists($key, $this->visible)) {
            return $this->visible[$key];
        }
        $ids = [];
        foreach (BeamUxEntry::query()->where('namespace', self::NAMESPACE)->get() as $entry) {
            if (app(PublicEntryGate::class)->chainForEntry($entry, BeamUxEntry::REALM_SITE, request()->user()) === null) {
                continue;
            }
            try {
                $this->refresh($entry);
                $ids[] = (string) $entry->getKey();
            } catch (\Throwable $exception) {
                Log::warning('Excluding invalid published Guide snapshot.', ['entry' => $entry->getKey(), 'pin' => $entry->getAttribute('published_version'), 'exception' => $exception]);
            }
        }

        $this->visible[$key] = $ids;

        return $ids;
    }

    public function invalidate(): void
    {
        $this->visible = [];
    }

    public function refresh(BeamUxEntry $entry): Guide
    {
        return DB::transaction(function () use ($entry): Guide {
            $entry = BeamUxEntry::query()->whereKey($entry->getKey())->lockForUpdate()->firstOrFail();
            abort_unless(app(PublicEntryGate::class)->chainForEntry($entry, BeamUxEntry::REALM_SITE, request()->user()) !== null, 404);
            $guide = Guide::query()->firstOrNew(['entry_id' => $entry->id]);
            if ($guide->exists && $guide->source_pin === $entry->getAttribute('published_version')) {
                return $guide;
            }
            $publication = app(EntryPublication::class);
            $body = $publication->publishedBodyOf($entry);
            if ($body === null || $entry->getAttribute('published_version') === null) {
                throw new \RuntimeException('Guide '.$entry->getAttribute('slug').' has no readable published snapshot.');
            }
            $settings = $publication->publishedSettingsOf($entry);
            if (MdxPageFile::applies($entry)) {
                if ($settings === null) {
                    throw new \RuntimeException('A blocks Guide has no published page settings.');
                }
                $fields = array_merge($settings->unclaimed, $settings->fields, ['title' => $settings->title]);
            } else {
                $fields = $body['frontmatter'] ?? [];
            }
            $canonical = [];
            foreach ($fields as $key => $value) {
                $canonical[FrontmatterParser::canonicalize($key)] = $value;
            }
            $metadata = app(FrontmatterResolver::class)->hydrate(new ParsedFrontmatter(fields: $canonical), GuideFrontmatterData::class);
            if (! $metadata instanceof GuideFrontmatterData) {
                throw new \LogicException('The declared Guide frontmatter shape was not returned.');
            }

            $category = Category::query()->firstOrCreate(['slug' => $metadata->category], ['name' => CategoryCatalog::nameFor($metadata->category)]);
            $guide = Guide::query()->updateOrCreate(['entry_id' => $entry->getKey()], ['slug' => $entry->getAttribute('slug'), 'source_pin' => $entry->getAttribute('published_version'), 'title' => $metadata->title, 'teaser' => $metadata->teaser, 'category_id' => $category->id, 'published_at' => $metadata->publishedAt, 'search_body' => (string) ($body['content'] ?? json_encode($body, JSON_THROW_ON_ERROR))]);
            $pivot = [];
            foreach ($metadata->calculators as $position => $association) {
                $calculator = Calculator::query()->where('slug', $association->slug)->firstOrFail();
                $pivot[$calculator->id] = ['mode' => $association->mode, 'anchor' => $association->anchor, 'position' => $position];
            }
            $guide->calculators()->sync($pivot);

            return $guide;
        }, attempts: 5);
    }
}
