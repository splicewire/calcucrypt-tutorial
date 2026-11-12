<?php

namespace App\Content\Guides;

use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Data;
use Splicewire\Beam\Mdx\Frontmatter\Contracts\DeclaresFrontmatterFields;
use Splicewire\Beam\Mdx\Frontmatter\Contracts\Frontmatter;
use Splicewire\Beam\Mdx\Frontmatter\Contracts\HydratesFromFrontmatter;
use Splicewire\Beam\Mdx\Frontmatter\FrontmatterParser;
use Splicewire\Beam\Mdx\Frontmatter\ParsedFrontmatter;

final class GuideFrontmatterData extends Data implements DeclaresFrontmatterFields, Frontmatter, HydratesFromFrontmatter
{
    /** @param list<GuideAssociationData> $calculators */
    public function __construct(public string $title, public ?string $teaser, public string $category, public string $publishedAt, public string $marking, public array $calculators) {}

    public static function fieldNames(): array
    {
        return ['title', 'teaser', 'category', 'published_at', 'workflow_marking', 'calculators_json', 'realm', 'segment'];
    }

    public static function fromFrontmatter(ParsedFrontmatter $parsed): static
    {
        $fields = [];
        foreach ($parsed->fields as $key => $value) {
            $fields[FrontmatterParser::canonicalize($key)] = $value;
        }
        validator($fields, [
            'title' => ['required', 'string', 'max:255'],
            'teaser' => ['nullable', 'string', 'max:2000'],
            'category' => ['required', 'string', 'max:255'],
            'published_at' => ['required', 'date'],
            'workflow_marking' => ['required', 'in:draft,published'],
            'calculators_json' => ['required', 'string', 'max:2000'],
        ])->validate();
        try {
            $associations = json_decode($fields['calculators_json'], true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw ValidationException::withMessages(['calculators_json' => 'Expected one JSON array on one line.']);
        }
        if (! is_array($associations) || ! array_is_list($associations)) {
            throw ValidationException::withMessages(['calculators_json' => 'Expected a JSON list of calculator associations.']);
        }
        validator(['calculators' => $associations], [
            'calculators' => ['array'],
            'calculators.*' => ['array:slug,mode,anchor'],
            'calculators.*.slug' => ['required', 'string', 'distinct'],
            'calculators.*.mode' => ['required', 'in:embed,link'],
            'calculators.*.anchor' => ['nullable', 'string'],
        ])->validate();
        $anchors = [];
        foreach ($associations as $association) {
            if ($association['mode'] === 'embed') {
                $anchor = $association['anchor'] ?? '';
                if ($anchor === '' || in_array($anchor, $anchors, true)) {
                    throw ValidationException::withMessages(['calculators_json' => 'Every embed needs its own nonempty anchor.']);
                }
                $anchors[] = $anchor;
            }
        }

        return new self($fields['title'], $fields['teaser'] ?? null, $fields['category'], $fields['published_at'], $fields['workflow_marking'], array_map(fn (array $row) => new GuideAssociationData($row['slug'], $row['mode'], $row['anchor'] ?? null), $associations));
    }
}
