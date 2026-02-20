<?php

namespace GaiaTools\ContentAccord\Dimensions;

use GaiaTools\ContentAccord\Contracts\ContextResolver;
use GaiaTools\ContentAccord\Contracts\NegotiationDimension;
use GaiaTools\ContentAccord\Enums\MissingVersionStrategy;
use GaiaTools\ContentAccord\Exceptions\MissingVersionException;
use GaiaTools\ContentAccord\Exceptions\UnsupportedVersionException;
use GaiaTools\ContentAccord\ValueObjects\ApiVersion;
use Illuminate\Http\Request;

final readonly class VersioningDimension implements NegotiationDimension
{
    /**
     * @param  ContextResolver  $resolver
     * @param  MissingVersionStrategy  $missingStrategy
     * @param  ApiVersion|null  $defaultVersion
     * @param  array<int>  $supportedVersions
     */
    public function __construct(
        private ContextResolver $resolver,
        private MissingVersionStrategy $missingStrategy,
        private ?ApiVersion $defaultVersion,
        private array $supportedVersions,
    ) {
    }

    public function key(): string
    {
        return 'version';
    }

    public function resolver(): ContextResolver
    {
        return $this->resolver;
    }

    public function validate(mixed $resolved, Request $request): bool
    {
        if (! $resolved instanceof ApiVersion) {
            throw UnsupportedVersionException::forVersion(
                new ApiVersion(0),
                $this->supportedVersions
            );
        }

        if (! in_array($resolved->major, $this->supportedVersions, true)) {
            throw UnsupportedVersionException::forVersion(
                $resolved,
                $this->supportedVersions
            );
        }

        return true;
    }

    public function fallback(Request $request): mixed
    {
        return match ($this->missingStrategy) {
            MissingVersionStrategy::Reject => throw new MissingVersionException(),
            MissingVersionStrategy::DefaultVersion => $this->defaultVersion
                ?? throw new MissingVersionException('No default version configured'),
            MissingVersionStrategy::LatestVersion => $this->resolveLatestVersion(),
            MissingVersionStrategy::Require => throw new MissingVersionException(
                $this->buildRequirementMessage()
            ),
        };
    }

    private function resolveLatestVersion(): ApiVersion
    {
        $latestMajor = max($this->supportedVersions);

        return new ApiVersion($latestMajor);
    }

    private function buildRequirementMessage(): string
    {
        $supported = implode(', ', array_map(fn ($v) => "v{$v}", $this->supportedVersions));

        return "API version is required. Supported versions: {$supported}";
    }
}
