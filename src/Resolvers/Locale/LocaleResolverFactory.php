<?php

namespace GaiaTools\ContentAccord\Resolvers\Locale;

use GaiaTools\ContentAccord\Contracts\ContextResolver;
use GaiaTools\ContentAccord\Resolvers\ChainedResolver;
use Illuminate\Container\Container;
use InvalidArgumentException;

final readonly class LocaleResolverFactory
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

        throw new InvalidArgumentException('No locale resolver configured. Set content-accord.locale.resolver to at least one resolver class.');
    }

    private function resolveResolver(mixed $resolver): ContextResolver
    {
        if ($resolver instanceof ContextResolver) {
            return $resolver;
        }

        if (! is_string($resolver) || $resolver === '') {
            throw new InvalidArgumentException('Configured resolver must be a class name, binding, or ContextResolver instance.');
        }

        $resolved = $this->buildResolverInstance($resolver);

        if (! $resolved instanceof ContextResolver) {
            throw new InvalidArgumentException('Configured resolver must implement ContextResolver.');
        }

        return $resolved;
    }

    private function buildResolverInstance(string $resolver): mixed
    {
        $strategies = is_array($this->config['strategies'] ?? null) ? $this->config['strategies'] : [];
        $header = is_array($strategies['header'] ?? null) ? $strategies['header'] : [];
        $query = is_array($strategies['query'] ?? null) ? $strategies['query'] : [];

        return match ($resolver) {
            AcceptLanguageLocaleResolver::class => new AcceptLanguageLocaleResolver,
            HeaderLocaleResolver::class => new HeaderLocaleResolver(
                $this->stringOrDefault($header['name'] ?? null, 'X-Locale'),
            ),
            QueryStringLocaleResolver::class => new QueryStringLocaleResolver(
                $this->stringOrDefault($query['parameter'] ?? null, 'locale'),
            ),
            default => $this->container->make($resolver),
        };
    }

    private function stringOrDefault(mixed $value, string $default): string
    {
        return is_string($value) && $value !== '' ? $value : $default;
    }
}
