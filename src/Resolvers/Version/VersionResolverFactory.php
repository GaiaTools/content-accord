<?php

namespace GaiaTools\ContentAccord\Resolvers\Version;

use GaiaTools\ContentAccord\Contracts\ContextResolver;
use GaiaTools\ContentAccord\Resolvers\ChainedResolver;
use Illuminate\Container\Container;
use InvalidArgumentException;

final readonly class VersionResolverFactory
{
    public function __construct(
        private Container $container,
        private array $config,
    ) {
    }

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

        $resolved = match ($resolver) {
            UriVersionResolver::class => new UriVersionResolver(
                $this->config['strategies']['uri']['parameter'] ?? 'version',
                $this->config['strategies']['uri']['prefix'] ?? 'v',
            ),
            HeaderVersionResolver::class => new HeaderVersionResolver($this->config['strategies']['header']['name'] ?? 'Api-Version'),
            AcceptHeaderVersionResolver::class => new AcceptHeaderVersionResolver($this->config['strategies']['accept']['vendor'] ?? 'myapp'),
            default => $this->container->make($resolver),
        };

        if (! $resolved instanceof ContextResolver) {
            throw new InvalidArgumentException('Configured resolver must implement ContextResolver.');
        }

        return $resolved;
    }
}
