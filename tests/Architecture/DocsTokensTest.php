<?php

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class DocsTokensTest extends TestCase
{
    public function test_the_docs_entrypoint_loads_the_scoped_docs_tokens(): void
    {
        $entrypoint = file_get_contents(__DIR__.'/../../resources/js/app.tsx');

        self::assertIsString($entrypoint);
        self::assertStringContainsString(
            "import '@splicewire/beam-ux/tokens-docs.css';",
            $entrypoint,
            'Docs need the scoped site-content hooks, including --beam-fg-muted, without recolouring the rest of the host.',
        );
    }
}
