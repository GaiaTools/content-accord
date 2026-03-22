<?php

namespace GaiaTools\ContentAccord\Resolvers\Version;

use Closure;
use GaiaTools\ContentAccord\Contracts\VersionResolver;
use GaiaTools\ContentAccord\Exceptions\InvalidVersionFormatException;
use GaiaTools\ContentAccord\ValueObjects\ApiVersion;
use Illuminate\Http\Request;

/**
 * Wraps any VersionResolver and maps alias strings to real version numbers
 * before delegating to the inner resolver.
 *
 * Example aliases: ['latest' => '3', 'stable' => '2']
 *
 * When the raw request string is a recognised alias key the mapped version is
 * returned directly, without delegating to the inner resolver. This lets
 * clients send `?version=latest` or `Api-Version: stable` and receive the
 * canonical version the alias points to.
 */
final readonly class AliasVersionResolver implements VersionResolver
{
    /**
     * @param  array<string, string>  $aliases  Map of alias => real version string
     * @param  Closure(Request): ?string  $rawExtractor  Returns the raw version string from the request
     */
    public function __construct(
        private VersionResolver $inner,
        private array $aliases,
        private Closure $rawExtractor,
    ) {}

    public function resolve(Request $request): ?ApiVersion
    {
        $raw = ($this->rawExtractor)($request);

        if (is_string($raw) && $raw !== '' && isset($this->aliases[$raw])) {
            try {
                return ApiVersion::parse($this->aliases[$raw]);
            } catch (InvalidVersionFormatException) {
                // Alias target is malformed — fall through to inner resolver
            }
        }

        return $this->inner->resolve($request);
    }
}
