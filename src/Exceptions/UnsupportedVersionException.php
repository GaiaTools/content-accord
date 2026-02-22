<?php

namespace GaiaTools\ContentAccord\Exceptions;

use GaiaTools\ContentAccord\ValueObjects\ApiVersion;

class UnsupportedVersionException extends ApiVersionException
{
    /**
     * @param  array<int, int>  $supportedVersions
     */
    public static function forVersion(ApiVersion $version, array $supportedVersions = []): self
    {
        $message = "Unsupported API version: {$version}";

        if (! empty($supportedVersions)) {
            $supported = implode(', ', array_map(fn ($v) => "v{$v}", $supportedVersions));
            $message .= ". Supported versions: {$supported}";
        }

        return new self($message);
    }
}
