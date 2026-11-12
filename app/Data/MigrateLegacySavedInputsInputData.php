<?php

namespace App\Data;

use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Data;

#[Title('Legacy Saved Inputs migration')]
class MigrateLegacySavedInputsInputData extends Data
{
    public function __construct(public bool $apply = false) {}
}
