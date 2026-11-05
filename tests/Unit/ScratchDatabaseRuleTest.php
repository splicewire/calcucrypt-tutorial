<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\ScratchDatabase;

/** The rule a test that rebuilds its database checks first, on every driver a host's suite may use. Reads no database. */
class ScratchDatabaseRuleTest extends TestCase
{
    /** @return array<string, array{string, string, mixed}> */
    public static function scratch(): array
    {
        return [
            'sqlite in memory' => ['sqlite', ':memory:', 'test_'],
            'sqlite in memory, no prefix needed' => ['sqlite', ':memory:', null],
            'pgsql under the default prefix' => ['pgsql', 'test_beam_starter', 'test_'],
            'pgsql under a host prefix' => ['pgsql', 'ttc_b33_beam', 'ttc_'],
        ];
    }

    /** @return array<string, array{string, string, mixed}> */
    public static function served(): array
    {
        return [
            'sqlite file' => ['sqlite', '/srv/app/database/database.sqlite', 'test_'],
            'sqlite empty name' => ['sqlite', '', 'test_'],
            'pgsql served database' => ['pgsql', 'beam', 'test_'],
            'pgsql shared test database outside the prefix' => ['pgsql', 'test_laravel_tower', 'ttc_'],
            'pgsql database equal to the bare prefix' => ['pgsql', 'test_', 'test_'],
            'pgsql with no prefix configured' => ['pgsql', 'test_beam', null],
            'pgsql with an empty prefix' => ['pgsql', 'beam', ''],
            'pgsql with a prefix not ending in an underscore' => ['pgsql', 'testbeam', 'test'],
            'mysql served database' => ['mysql', 'beam', 'test_'],
        ];
    }

    #[DataProvider('scratch')]
    public function test_a_scratch_database_is_accepted(string $driver, string $database, mixed $prefix): void
    {
        $this->assertNull(ScratchDatabase::refusal($driver, $database, $prefix));
    }

    #[DataProvider('served')]
    public function test_any_other_database_is_refused_with_a_reason(string $driver, string $database, mixed $prefix): void
    {
        $this->assertIsString(ScratchDatabase::refusal($driver, $database, $prefix));
    }
}
