<?php

declare(strict_types=1);

namespace App\Data\Pages;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The props a realm's frame console mount passes to `@splicewire/beam-inertia`'s `pages/frame/console`.
 *
 * Mirrors that package's `FrameRealmContext` (`src/frame/realm.tsx`) field for field, nullability
 * included: `realm` is `string | null` there because a host may mount one unscoped console. A plain
 * array here let a renamed key break the console at runtime with nothing failing first.
 * `OperatorFrameManifestTest` pins the rendered props to exactly these three keys.
 */
#[TypeScript]
final class FrameConsolePageData extends Data
{
    public function __construct(
        public ?string $realm,
        public string $basename,
        public string $manifestUrl,
    ) {}
}
