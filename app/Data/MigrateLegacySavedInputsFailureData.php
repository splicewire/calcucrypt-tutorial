<?php

namespace App\Data;

use Schemastud\DataSchemas\Attributes\MapValues;
use Spatie\LaravelData\Data;

class MigrateLegacySavedInputsFailureData extends Data
{
    /** @param array<string, list<string>> $errors */
    public function __construct(public string $message, #[MapValues('array')] public array $errors) {}
}
