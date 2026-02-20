<?php

namespace GaiaTools\ContentAccord\Exceptions;

class InvalidVersionFormatException extends ApiVersionException
{
    public static function forValue(string $value): self
    {
        return new self("Invalid version format: '{$value}'. Expected format: '1', '1.2', 'v1', or 'v1.2'");
    }
}
