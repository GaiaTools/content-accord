<?php

namespace GaiaTools\ContentAccord\Resolvers\Version;

use GaiaTools\ContentAccord\Contracts\VersionResolver;
use GaiaTools\ContentAccord\Exceptions\InvalidVersionFormatException;
use GaiaTools\ContentAccord\ValueObjects\ApiVersion;
use Illuminate\Http\Request;

final readonly class UriVersionResolver implements VersionResolver
{
    public function __construct(
        private string $parameterName = 'version',
        private string $prefix = 'v',
    ) {
    }

    public function resolve(Request $request): ?ApiVersion
    {
        $route = $request->route();

        if (! $route) {
            return null;
        }

        $versionString = $route->parameter($this->parameterName);

        if ($versionString && is_string($versionString)) {
            return $this->parseFromParameter($versionString);
        }

        $versionString = $route->getAction('api_version');

        if (! $versionString || ! is_string($versionString)) {
            return null;
        }

        try {
            return ApiVersion::parse($versionString);
        } catch (InvalidVersionFormatException) {
            return null;
        }
    }

    private function parseFromParameter(string $value): ?ApiVersion
    {
        if ($this->prefix !== '') {
            if (! str_starts_with(strtolower($value), strtolower($this->prefix))) {
                return null;
            }

            $value = substr($value, strlen($this->prefix));
        }

        try {
            return ApiVersion::parse($value);
        } catch (InvalidVersionFormatException) {
            return null;
        }
    }
}
