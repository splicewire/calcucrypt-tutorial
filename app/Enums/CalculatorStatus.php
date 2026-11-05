<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A Calculator's publish state. Mirrors the Post publish-window model so a calculator and
 * a guide gate the same way.
 */
#[TypeScript]
enum CalculatorStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
