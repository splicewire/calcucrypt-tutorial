<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * purchase-walkthrough BUY-05, phase C (option (a), owner 2026-10-05): this starter DECLARES its registration door,
 * closed by default. ACCOUNT_REGISTRATION=open opens it. Read from the config file with the env cleared, as a fresh
 * install reads it.
 */
class RegistrationDoorDefaultTest extends TestCase
{
    private function registration(?string $env): mixed
    {
        $before = getenv('ACCOUNT_REGISTRATION');
        $env === null ? putenv('ACCOUNT_REGISTRATION') : putenv("ACCOUNT_REGISTRATION={$env}");
        $saved = [$_ENV['ACCOUNT_REGISTRATION'] ?? null, $_SERVER['ACCOUNT_REGISTRATION'] ?? null];
        unset($_ENV['ACCOUNT_REGISTRATION'], $_SERVER['ACCOUNT_REGISTRATION']);

        try {
            return (require __DIR__.'/../../config/beam/accounts.php')['doors']['registration'] ?? null;
        } finally {
            $before === false ? putenv('ACCOUNT_REGISTRATION') : putenv("ACCOUNT_REGISTRATION={$before}");
            [$e, $s] = $saved;
            if ($e !== null) {
                $_ENV['ACCOUNT_REGISTRATION'] = $e;
            }
            if ($s !== null) {
                $_SERVER['ACCOUNT_REGISTRATION'] = $s;
            }
        }
    }

    public function test_a_fresh_install_declares_registration_closed()
    {
        $this->assertSame('closed', $this->registration(null));
    }

    public function test_the_env_opens_it()
    {
        $this->assertSame('open', $this->registration('open'));
    }
}
