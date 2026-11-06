<?php

namespace Tests\Support;

/**
 * Whether a test may rebuild a database, on whatever driver the host's suite uses. A scratch database is an in-memory
 * sqlite database, which exists only in this process, or a server database whose name carries laravel-beam-dev's
 * scratch prefix (`beam.dev.prefix`, `BEAM_DEV_DB_PREFIX`). That prefix is how `isolated-test-db` names its databases
 * and the line `drop-db` guards. The rule fails closed: with no well-formed prefix, every server database is refused.
 */
final class ScratchDatabase
{
    /** Why `$database` is not a scratch database, or null when it is. */
    public static function refusal(string $driver, string $database, mixed $prefix): ?string
    {
        if ($driver === 'sqlite') {
            return $database === ':memory:' ? null : "sqlite [{$database}] is not an in-memory database";
        }

        return match (true) {
            ! is_string($prefix) || preg_match('/^[a-z][a-z0-9_]*_$/', $prefix) !== 1 => 'the scratch prefix (beam.dev.prefix) is empty or malformed: '.var_export($prefix, true),
            ! str_starts_with($database, $prefix) || $database === $prefix => "{$driver} [{$database}] is not a scratch database ({$prefix}*)",
            default => null,
        };
    }
}
