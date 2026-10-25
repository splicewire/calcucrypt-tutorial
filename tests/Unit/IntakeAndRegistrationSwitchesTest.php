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
        $prior = getenv('BEAM_INTAKE_ENABLED');
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
            $door($prior === false ? null : $prior);
        }
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
