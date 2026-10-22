<?php

namespace App\Backings;

use App\ExternalRequests\SourceClient;
use Illuminate\Validation\ValidationException;
use Splicewire\Beam\Particle\Backing\CollectionBacking;
use Splicewire\Beam\Particle\Backing\DeclaredFacet;
use Splicewire\Beam\Particle\Backing\DeclaresFilterVocabulary;
use Splicewire\Beam\Particle\Backing\FilterVocabulary;
use Splicewire\Beam\Particle\Backing\ResolvedRecord;
use Splicewire\Beam\Particle\Backing\ResolvesRecord;

final class ExternalRequestBacking extends CollectionBacking implements DeclaresFilterVocabulary, ResolvesRecord
{
    public function __construct(private SourceClient $source) {}

    protected function rows(array $filters): iterable
    {
        if (array_diff(array_keys($filters), ['status']) !== [] || (isset($filters['status']) && ! in_array($filters['status'], ['open', 'closed'], true))) {
            throw ValidationException::withMessages(['filter' => 'Only open/closed status is supported.']);
        }
        foreach ($this->source->records() as $row) {
            if (! isset($filters['status']) || $filters['status'] === $row->status) {
                yield $row;
            }
        }
    }

    protected function sortKey(): string
    {
        return 'updatedAt';
    }

    public function resolve(string $id, array $filters): ?ResolvedRecord
    {
        // Frame always includes its optional discriminator. Qualified IDs supply that identity here.
        unset($filters['source']);
        foreach ($this->rows($filters) as $row) {
            if ($row->id === $id) {
                return new ResolvedRecord($row);
            }
        }

        return null;
    }

    public function filterVocabulary(): FilterVocabulary
    {
        return FilterVocabulary::of(DeclaredFacet::exact('status', inline: [['value' => 'open', 'label' => 'Open'], ['value' => 'closed', 'label' => 'Closed']]));
    }
}
