<?php

namespace GaiaTools\ContentAccord\Resolvers\Version;

use GaiaTools\ContentAccord\Contracts\VersionResolver;
use GaiaTools\ContentAccord\Exceptions\InvalidVersionFormatException;
use GaiaTools\ContentAccord\ValueObjects\ApiVersion;
use Illuminate\Http\Request;

final readonly class QueryStringVersionResolver implements VersionResolver
{
    public function __construct(
        private string $parameterName = 'version',
    ) {}

    public function resolve(Request $request): ?ApiVersion
    {
        $versionString = $request->query($this->parameterName);

        if (! is_string($versionString) || trim($versionString) === '') {
            return null;
        }

        try {
            return ApiVersion::parse(trim($versionString));
        } catch (InvalidVersionFormatException) {
            return null;
        }
    }
}
