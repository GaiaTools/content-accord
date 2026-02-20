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
    ) {
    }

    public function resolve(Request $request): ?ApiVersion
    {
        $route = $request->route();

        if (! $route) {
            return null;
        }

        $versionString = $route->parameter($this->parameterName);

        if (! $versionString || ! is_string($versionString)) {
            $versionString = $route->getAction('api_version');
        }

        if (! $versionString || ! is_string($versionString)) {
            return null;
        }

        try {
            return ApiVersion::parse($versionString);
        } catch (InvalidVersionFormatException) {
            return null;
        }
    }
}
