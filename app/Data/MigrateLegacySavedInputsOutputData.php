<?php

namespace App\Data;

use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Data;

#[Title('Legacy Saved Inputs migration result')]
class MigrateLegacySavedInputsOutputData extends Data
{
    public function __construct(
        public bool $dryRun,
        public bool $sourcePresent,
        public int $examined,
        public int $created,
        public int $wouldCreate,
        public int $skipped,
    ) {}
}
