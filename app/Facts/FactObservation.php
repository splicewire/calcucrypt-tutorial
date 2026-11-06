<?php

declare(strict_types=1);

namespace App\Facts;

use Illuminate\Support\Carbon;

/**
 * One raw observation as a Connector reports it, before it is stored as a Fact. The
 * internal hand-off shape between a Connector and the FactStore.
 */
final readonly class FactObservation
{
    /**
     * @param  array<string, mixed>|null  $raw
     */
    public function __construct(
        public float $value,
        public string $source,
        public Carbon $asOf,
        public ?array $raw = null,
    ) {}
}
