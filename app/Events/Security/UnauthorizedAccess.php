<?php

namespace App\Events\Security;

class UnauthorizedAccess
{
    public function __construct(protected array $payload = [])
    {
    }

    public function __get(string $name)
    {
        return $this->payload[$name] ?? null;
    }

    public function toArray(): array
    {
        return $this->payload;
    }
}
