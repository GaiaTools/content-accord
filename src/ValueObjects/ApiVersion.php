<?php

namespace GaiaTools\ContentAccord\ValueObjects;

use GaiaTools\ContentAccord\Exceptions\InvalidVersionFormatException;
use Stringable;

final readonly class ApiVersion implements Stringable
{
    public function __construct(
        public int $major,
        public int $minor = 0,
    ) {}

    /**
     * Parse version from various formats: "1", "1.2", "v1", "v1.2"
     *
     * @throws InvalidVersionFormatException
     */
    public static function parse(string $value): self
    {
        $value = trim($value);

        if ($value === '') {
            throw InvalidVersionFormatException::forValue($value);
        }

        // Remove optional 'v' prefix
        $normalized = preg_replace('/^v/i', '', $value);
        if ($normalized === null) {
            throw InvalidVersionFormatException::forValue($value);
        }

        // Match version pattern: major or major.minor
        if (! preg_match('/^(-?\d+)(?:\.(\d+))?$/', $normalized, $matches)) {
            throw InvalidVersionFormatException::forValue($value);
        }

        $major = (int) $matches[1];
        $minor = isset($matches[2]) ? (int) $matches[2] : 0;

        if ($major < 0 || $minor < 0) {
            throw InvalidVersionFormatException::forValue($value);
        }

        return new self($major, $minor);
    }

    /**
     * Whether this version satisfies the requested version.
     * Compares major only — minor is metadata for middleware/transformers.
     */
    public function satisfies(self $requested): bool
    {
        return $this->major === $requested->major;
    }

    public function isGreaterThan(self $other): bool
    {
        if ($this->major !== $other->major) {
            return $this->major > $other->major;
        }

        return $this->minor > $other->minor;
    }

    public function isLessThan(self $other): bool
    {
        if ($this->major !== $other->major) {
            return $this->major < $other->major;
        }

        return $this->minor < $other->minor;
    }

    public function equals(self $other): bool
    {
        return $this->major === $other->major
            && $this->minor === $other->minor;
    }

    public function toString(): string
    {
        return "{$this->major}.{$this->minor}";
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
