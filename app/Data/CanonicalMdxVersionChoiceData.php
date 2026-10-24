<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class CanonicalMdxVersionChoiceData extends Data
{
    public function __construct(public string $id, public string $label) {}
}
