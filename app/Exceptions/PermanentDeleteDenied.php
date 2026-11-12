<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when the permanent delete policy refuses a delete. The reasons are the
 * tables holding the record, so an admin is told what to clear instead of being
 * shown a database constraint error.
 */
class PermanentDeleteDenied extends RuntimeException
{
    /** @param list<string> $reasons */
    public function __construct(string $message, private readonly array $reasons = [])
    {
        parent::__construct($message);
    }

    /** @return list<string> */
    public function reasons(): array
    {
        return $this->reasons;
    }
}
