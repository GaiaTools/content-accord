<?php

namespace GaiaTools\ContentAccord\Http;

final class NegotiatedContext
{
    /** @var array<string, mixed> */
    private array $resolved = [];

    public function set(string $key, mixed $value): void
    {
        $this->resolved[$key] = $value;
    }

    public function get(string $key): mixed
    {
        return $this->resolved[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->resolved);
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->resolved;
    }
}
