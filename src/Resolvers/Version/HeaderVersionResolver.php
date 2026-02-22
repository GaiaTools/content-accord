<?php

namespace GaiaTools\ContentAccord\Resolvers\Version;

use GaiaTools\ContentAccord\Contracts\VersionResolver;
use GaiaTools\ContentAccord\Exceptions\InvalidVersionFormatException;
use GaiaTools\ContentAccord\ValueObjects\ApiVersion;
use Illuminate\Http\Request;

final readonly class HeaderVersionResolver implements VersionResolver
{
    public function __construct(
        private string $headerName = 'Api-Version',
    ) {}

    public function resolve(Request $request): ?ApiVersion
    {
        $versionString = $request->header($this->headerName);

        if (! $versionString || ! is_string($versionString)) {
            return null;
        }

        $versionString = trim($versionString);

        if ($versionString === '') {
            return null;
        }

        try {
            return ApiVersion::parse($versionString);
        } catch (InvalidVersionFormatException) {
            return null;
        }
    }
}
