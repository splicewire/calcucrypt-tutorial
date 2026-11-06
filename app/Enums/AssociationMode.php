<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * How a Guide (Post) relates to a Calculator: rendered interactive inline (embed) or
 * referenced via a CTA to its own tool page (link). CONTEXT.md "Association".
 */
#[TypeScript]
enum AssociationMode: string
{
    case Embed = 'embed';
    case Link = 'link';
}
