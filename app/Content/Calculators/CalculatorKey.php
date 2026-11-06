<?php

declare(strict_types=1);

namespace App\Content\Calculators;

use InvalidArgumentException;

/**
 * The Calculator Key grammar (CONTEXT.md; ADR 0005): exactly two lowercase-kebab segments,
 * `<domain>.<computation>`, the domain on the closed {@see Domain} list. A strict subset of the popcorn
 * kernel's key pattern: no `_` or `:` (the registry-keys convention reserves those for segments mirrored
 * from an outside authority, and no calculator segment is mirrored).
 *
 * The whole key is the identity. Its second segment alone is only a name, unique within its domain.
 */
class CalculatorKey
{
    /** One kebab segment: `[a-z0-9]+(-[a-z0-9]+)*`. */
    public const SEGMENT = '[a-z0-9]+(?:-[a-z0-9]+)*';

    /** Why `$key` is not a legal Calculator Key, or null when it is. */
    public static function violation(string $key): ?string
    {
        $segments = explode('.', $key);

        if (count($segments) !== 2) {
            return sprintf('has %d segment(s); a Calculator Key has exactly two, `<domain>.<computation>`', count($segments));
        }

        foreach ($segments as $segment) {
            if (preg_match('/\A'.self::SEGMENT.'\z/', $segment) !== 1) {
                return sprintf('segment `%s` is not lowercase kebab', $segment);
            }
        }

        if ($segments[0] === Domain::COMPOSITIONS) {
            return sprintf('`%s` is reserved for user compositions, which never resolve through this registry', Domain::COMPOSITIONS);
        }

        if (Domain::tryFrom($segments[0]) === null) {
            return sprintf('domain `%s` is not on the closed list (%s)', $segments[0], implode(', ', Domain::values()));
        }

        return null;
    }

    public static function isValid(string $key): bool
    {
        return self::violation($key) === null;
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function assertValid(string $key, string $owner): void
    {
        $violation = self::violation($key);

        if ($violation !== null) {
            throw new InvalidArgumentException(sprintf('`%s` declares Calculator Key `%s`, which %s.', $owner, $key, $violation));
        }
    }
}
