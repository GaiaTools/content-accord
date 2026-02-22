<?php

namespace GaiaTools\ContentAccord\Resolvers\Version;

use GaiaTools\ContentAccord\Contracts\VersionResolver;
use GaiaTools\ContentAccord\Exceptions\InvalidVersionFormatException;
use GaiaTools\ContentAccord\ValueObjects\ApiVersion;
use Illuminate\Http\Request;

final readonly class AcceptHeaderVersionResolver implements VersionResolver
{
    public function __construct(
        private string $vendor,
    ) {}

    public function resolve(Request $request): ?ApiVersion
    {
        $acceptHeader = $request->header('Accept');

        if (! $acceptHeader || ! is_string($acceptHeader)) {
            return null;
        }

        // Split by comma to handle multiple media types
        $mediaTypes = array_map('trim', explode(',', $acceptHeader));

        foreach ($mediaTypes as $mediaType) {
            $version = $this->extractVersionFromMediaType($mediaType);

            if ($version !== null) {
                return $version;
            }
        }

        return null;
    }

    private function extractVersionFromMediaType(string $mediaType): ?ApiVersion
    {
        // Try vendor format: application/vnd.{vendor}.v{version}+{format}
        // Or: application/vnd.{vendor}.v{version}
        $vendorPattern = '/^application\/vnd\.'.preg_quote($this->vendor, '/').'\.v([\d.]+)(?:\+\w+)?/i';

        if (preg_match($vendorPattern, $mediaType, $matches)) {
            return $this->parseVersion($matches[1]);
        }

        // Try parameter format: application/vnd.{vendor}+{format};version={version}
        $parameterPattern = '/^application\/vnd\.'.preg_quote($this->vendor, '/').'(?:\+\w+)?;\s*version=([\d.]+)/i';

        if (preg_match($parameterPattern, $mediaType, $matches)) {
            return $this->parseVersion($matches[1]);
        }

        return null;
    }

    private function parseVersion(string $versionString): ?ApiVersion
    {
        try {
            return ApiVersion::parse($versionString);
        } catch (InvalidVersionFormatException) {
            return null;
        }
    }
}
