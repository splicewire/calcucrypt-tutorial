<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * The starter receipt (2026-10-25): the server and the Vite build disagreed. The editor registered the register island
 * while the registration door was closed, and never registered the page form while the intake door was open, because
 * the build named neither switch. Each door is one env switch now, and the build reads its mirror.
 */
class IntakeAndRegistrationSwitchesTest extends TestCase
{
    public function test_the_intake_door_follows_beam_intake_enabled(): void
    {
        $prior = [getenv('BEAM_INTAKE_ENABLED'), $_ENV['BEAM_INTAKE_ENABLED'] ?? null, $_SERVER['BEAM_INTAKE_ENABLED'] ?? null];
        $door = function (?string $value): bool {
            putenv($value === null ? 'BEAM_INTAKE_ENABLED' : "BEAM_INTAKE_ENABLED={$value}");
            if ($value === null) {
                unset($_ENV['BEAM_INTAKE_ENABLED'], $_SERVER['BEAM_INTAKE_ENABLED']);
            } else {
                $_ENV['BEAM_INTAKE_ENABLED'] = $_SERVER['BEAM_INTAKE_ENABLED'] = $value;
            }

            return (require dirname(__DIR__, 2).'/config/beam/core.php')['intake']['enabled'];
        };

        try {
            $this->assertFalse($door(null), 'the door stays opt-in');
            $this->assertTrue($door('true'));
            $this->assertFalse($door('false'));
        } finally {
            // Put back what the run had, in all three places env() reads, for the tests after this one.
            [$process, $env, $server] = $prior;
            putenv($process === false ? 'BEAM_INTAKE_ENABLED' : "BEAM_INTAKE_ENABLED={$process}");
            if ($env === null) {
                unset($_ENV['BEAM_INTAKE_ENABLED']);
            } else {
                $_ENV['BEAM_INTAKE_ENABLED'] = $env;
            }
            if ($server === null) {
                unset($_SERVER['BEAM_INTAKE_ENABLED']);
            } else {
                $_SERVER['BEAM_INTAKE_ENABLED'] = $server;
            }
        }
    }

    public function test_mdx_pages_holding_blocks_register_the_kit_this_starter_renders(): void
    {
        // The starter receipt: with BEAM_UX_MDX_HOLDS_BLOCKS on, the canvas refused every MDX page, because their profile
        // offered the beam-mdx kit (and an Artifact this starter cannot render) while the editor registered none of it.
        $root = dirname(__DIR__, 2);
        $app = file_get_contents($root.'/resources/js/app.tsx');

        $kit = (require $root.'/config/beam/mdx.php')['kit'];
        $this->assertFalse($kit['artifact'], 'the starter has no <Artifact> renderer');
        // The rerun: Ref comes only from beam-mdx's root entry, and importing it crashed the starter's bundle on boot.
        $this->assertFalse($kit['ref'], 'the starter has no <Ref> renderer');
        $this->assertStringContainsString('VITE_BEAM_UX_MDX_HOLDS_BLOCKS="${BEAM_UX_MDX_HOLDS_BLOCKS}"', file_get_contents($root.'/.env.example'));
        $this->assertStringContainsString("components: import.meta.env.VITE_BEAM_UX_MDX_HOLDS_BLOCKS === 'true'", $app);
        $registered = substr($app, strpos($app, 'components:'), strpos($app, 'pages:') - strpos($app, 'components:'));
        foreach (['Callout', 'Steps', 'Step', 'Figure', 'Terminal', 'SectionLanding', 'CardGrid', 'Card', 'DoctorOutput', 'FileTree'] as $component) {
            $this->assertMatchesRegularExpression('/\b'.$component.'\b/', $registered, "{$component} is registered");
        }
        $this->assertDoesNotMatchRegularExpression('/\b(Artifact|Ref)\b/', $app);
        $this->assertStringNotContainsString("from '@splicewire/beam-mdx';", $app, 'no import from the root entry');
    }

    public function test_mdx_pages_holding_blocks_register_what_the_shipped_docs_pages_place(): void
    {
        // EP-42 (starter room, 10-27): with BEAM_UX_MDX_HOLDS_BLOCKS on, the canvas refused /docs, /docs/api and /docs/mcp.
        // Their profile also offers ApiReference (laravel-beam-docs) and SiteNav and ManifestTable (laravel-beam-ux), which
        // the packages' shipped docs pages place. beam-inertia registers the last two where the host says its MDX pages
        // hold blocks; ApiReference is the starter's, beside the kit.
        $root = dirname(__DIR__, 2);
        $app = file_get_contents($root.'/resources/js/app.tsx');

        $this->assertStringContainsString("mdxHoldsBlocks: import.meta.env.VITE_BEAM_UX_MDX_HOLDS_BLOCKS === 'true'", $app);
        $registered = substr($app, strpos($app, 'components:'), strpos($app, 'pages:') - strpos($app, 'components:'));
        $this->assertMatchesRegularExpression('/\bApiReference\b/', $registered, 'ApiReference is registered where MDX pages hold blocks');

        // The packages convert their own unedited pages when they are seeded again. Turned on without that, those pages
        // keep MDX source while they declare blocks, and the canvas cannot draw what they place.
        $this->assertStringContainsString('splicewire:beam:seed', file_get_contents($root.'/.env.example'));
    }

    public function test_the_vite_build_mirrors_both_doors(): void
    {
        $root = dirname(__DIR__, 2);
        $env = file_get_contents($root.'/.env.example');

        $this->assertStringContainsString('VITE_BEAM_INTAKE_ENABLED="${BEAM_INTAKE_ENABLED}"', $env);
        $this->assertStringContainsString('VITE_ACCOUNT_REGISTRATION="${ACCOUNT_REGISTRATION}"', $env);
        $this->assertStringContainsString("intakeForms: import.meta.env.VITE_BEAM_INTAKE_ENABLED === 'true'", file_get_contents($root.'/resources/js/app.tsx'));
        $this->assertStringContainsString("import.meta.env.VITE_ACCOUNT_REGISTRATION === 'open'", file_get_contents($root.'/resources/js/beam.ts'));
    }
}
