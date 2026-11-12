<?php

namespace App\Support;

use Closure;

class SchoolContext
{
    private ?int $schoolId = null;

    public function id(): ?int
    {
        return $this->schoolId;
    }

    public function has(): bool
    {
        return $this->schoolId !== null;
    }

    public function set(?int $schoolId): void
    {
        $this->schoolId = $schoolId;
    }

    public function clear(): void
    {
        $this->schoolId = null;
    }

    /**
     * Run a callback inside an explicit school context and always restore
     * the previous context, including when the callback throws.
     */
    public function run(?int $schoolId, Closure $callback): mixed
    {
        $previous = $this->schoolId;

        try {
            $this->schoolId = $schoolId;

            return $callback($this);
        } finally {
            $this->schoolId = $previous;
        }
    }
}
