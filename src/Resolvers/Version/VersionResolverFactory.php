<?php

namespace GaiaTools\ContentAccord\Resolvers\Version;

use GaiaTools\ContentAccord\Contracts\ContextResolver;
use GaiaTools\ContentAccord\Resolvers\ChainedResolver;
use Illuminate\Container\Container;
use InvalidArgumentException;

final readonly class VersionResolverFactory
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private Container $container,
        private array $config,
    ) {}

    public function build(): ContextResolver
    {
        $resolverConfig = $this->config['resolver'] ?? null;

        if (is_array($resolverConfig)) {
            $resolvers = array_map(fn ($resolver) => $this->resolveResolver($resolver), $resolverConfig);

            return new ChainedResolver($resolvers);
        }

        if (is_string($resolverConfig) && $resolverConfig !== '') {
            return $this->resolveResolver($resolverConfig);
        }

        throw new InvalidArgumentException('No version resolver configured. Set content-accord.versioning.resolver to at least one resolver class.');
    }

    private function resolveResolver(mixed $resolver): ContextResolver
    {
        if ($resolver instanceof ContextResolver) {
            return $resolver;
        }

        if (! is_string($resolver) || $resolver === '') {
            throw new InvalidArgumentException('Configured resolver must be a class name, binding, or ContextResolver instance.');
        }

        $strategies = $this->config['strategies'] ?? [];
        if (! is_array($strategies)) {
            $strategies = [];
        }

        $uri = is_array($strategies['uri'] ?? null) ? $strategies['uri'] : [];
        $header = is_array($strategies['header'] ?? null) ? $strategies['header'] : [];
        $accept = is_array($strategies['accept'] ?? null) ? $strategies['accept'] : [];

        $uriParameter = $uri['parameter'] ?? 'version';
        if (! is_string($uriParameter) || $uriParameter === '') {
            $uriParameter = 'version';
        }

        $uriPrefix = $uri['prefix'] ?? 'v';
        if (! is_string($uriPrefix) || $uriPrefix === '') {
            $uriPrefix = 'v';
        }

        $headerName = $header['name'] ?? 'Api-Version';
        if (! is_string($headerName) || $headerName === '') {
            $headerName = 'Api-Version';
        }

        $vendor = $accept['vendor'] ?? 'myapp';
        if (! is_string($vendor) || $vendor === '') {
            $vendor = 'myapp';
        }

        $resolved = match ($resolver) {
            UriVersionResolver::class => new UriVersionResolver($uriParameter, $uriPrefix),
            HeaderVersionResolver::class => new HeaderVersionResolver($headerName),
            AcceptHeaderVersionResolver::class => new AcceptHeaderVersionResolver($vendor),
            default => $this->container->make($resolver),
        };

        if (! $resolved instanceof ContextResolver) {
            throw new InvalidArgumentException('Configured resolver must implement ContextResolver.');
        }

        return $resolved;
    }
}
