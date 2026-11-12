<?php

namespace App\Content\Guides;

use App\Content\Calculators\CalculatorRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Splicewire\Beam\Mdx\Frontmatter\FrontmatterParser;
use Splicewire\Beam\Mdx\Frontmatter\FrontmatterResolver;
use Splicewire\Beam\Ux\Disk\RegisterEntriesFromDisk;
use Splicewire\Beam\Ux\Models\BeamUxEntry;
use Splicewire\Beam\Ux\Provenance\Provenance;
use Splicewire\Beam\Ux\Type\UxType;

class GuideImporter
{
    use \Splicewire\Beam\Write\AsSystemWriter;

    public function import(?string $root = null): void
    {
        $root ??= resource_path('content');
        $files = glob($root.'/guides/*.mdx') ?: [];
        if ($files === []) {
            throw new \RuntimeException('No authored Guides found.');
        }
        $slugs = array_map(fn ($definition) => $definition->slug(), app(CalculatorRegistry::class)->all());
        foreach ($files as $file) {
            try {
                $source = (string) file_get_contents($file);
                $metadata = $this->check($source, $slugs);
                $entry = BeamUxEntry::query()->where('namespace', GuideCatalog::NAMESPACE)->where('slug', basename($file, '.mdx'))->first();
                if ($entry !== null && ($entry->getAttribute('origin') !== Provenance::disk('guides/'.basename($file)) || $entry->getAttribute('asserted_hash') !== Provenance::hash($metadata->title, RegisterEntriesFromDisk::asWritten($entry, $source)))) {
                    throw new \RuntimeException('Authored source differs from its registered UX baseline; reconcile it through UX publication before importing. No existing entry was overwritten.');
                }
            } catch (\Throwable $exception) {
                throw new \RuntimeException(basename($file).': '.$exception->getMessage(), previous: $exception);
            }
        }
        DB::transaction(function () use ($root, $files): void {
            $result = $this->asSystemWriter(fn () => app(RegisterEntriesFromDisk::class)->scan($root, default: UxType::Page, only: array_map(fn ($file) => 'guides/'.basename($file), $files)));
            if ($result['failed'] !== [] || $result['unresolved'] !== []) {
                throw new \RuntimeException('Guide import failed: '.json_encode($result['failed'] + $result['unresolved'], JSON_THROW_ON_ERROR));
            }
            app(GuideCatalog::class)->invalidate();
            app(GuideCatalog::class)->visibleEntryIds();
        });
    }

    /** @param list<string> $calculatorSlugs */
    public function check(string $source, array $calculatorSlugs): GuideFrontmatterData
    {
        if (! preg_match('/\A---\r?\n(.*?)\r?\n---(?:\r?\n|$)/s', $source, $block)) {
            throw ValidationException::withMessages(['frontmatter' => 'Expected a closed, leading frontmatter block.']);
        }
        $seen = [];
        foreach (explode("\n", str_replace("\r\n", "\n", $block[1])) as $line) {
            if (trim($line) === '' || str_starts_with(ltrim($line), '#')) {
                continue;
            }
            if (! preg_match('/^([A-Za-z0-9_-]+):[ \t]*(.*)$/', $line, $match)) {
                throw ValidationException::withMessages(['frontmatter' => 'Only flat scalar metadata is supported; use calculators_json for associations.']);
            }
            $key = FrontmatterParser::canonicalize($match[1]);
            if (isset($seen[$key]) || ! in_array($key, GuideFrontmatterData::fieldNames(), true)) {
                throw ValidationException::withMessages([$key => 'Duplicate or unsupported Guide field; use calculators_json for associations.']);
            }
            $value = trim($match[2], " \t\"'");
            if (mb_strlen($value) > 2000 || str_contains($value, "\n") || str_contains($value, "\r")) {
                throw ValidationException::withMessages([$key => 'Guide scalar metadata must be printable on one line within 2000 characters.']);
            }
            $seen[$key] = true;
        }
        $parsed = app(FrontmatterParser::class)->parse($source);
        $metadata = app(FrontmatterResolver::class)->hydrate($parsed, GuideFrontmatterData::class);
        if (! $metadata instanceof GuideFrontmatterData) {
            throw new \LogicException('The declared Guide frontmatter shape was not returned.');
        }
        if ($metadata->marking === 'published' && \Illuminate\Support\Carbon::parse($metadata->publishedAt)->isFuture()) {
            throw ValidationException::withMessages(['published_at' => 'A future Guide must import as draft and use a scheduled UX Release.']);
        }
        foreach ($metadata->calculators as $association) {
            if (! in_array($association->slug, $calculatorSlugs, true) || ($association->mode === 'embed' && ! str_contains($parsed->content, 'data-embed="'.$association->anchor.'"'))) {
                throw ValidationException::withMessages(['calculators_json' => 'Unknown calculator or missing embed anchor.']);
            }
        }

        return $metadata;
    }
}
