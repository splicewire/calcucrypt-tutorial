<?php

namespace App\ExternalRequests;

use RuntimeException;

final class SourceFailure extends RuntimeException
{
    public function __construct(public readonly int $status, string $message)
    {
        parent::__construct($message);
    }
}
