<?php

namespace GaiaTools\ContentAccord\Resolvers\Version;

use Closure;
use GaiaTools\ContentAccord\Contracts\ContextResolver;
use GaiaTools\ContentAccord\Contracts\VersionResolver;
use GaiaTools\ContentAccord\Resolvers\ChainedResolver;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
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

        $strategies = is_array($this->config['strategies'] ?? null) ? $this->config['strategies'] : [];
        $resolved = $this->buildResolverInstance($resolver, $strategies);

        if (! $resolved instanceof ContextResolver) {
            throw new InvalidArgumentException('Configured resolver must implement ContextResolver.');
        }

        if ($resolved instanceof VersionResolver) {
            $resolved = $this->maybeWrapWithAliases($resolved, $resolver, $strategies);
        }

        return $resolved;
    }

    /**
     * @param  array<string, mixed>  $strategies
     */
    private function buildResolverInstance(string $resolver, array $strategies): mixed
    {
        $uri = is_array($strategies['uri'] ?? null) ? $strategies['uri'] : [];
        $header = is_array($strategies['header'] ?? null) ? $strategies['header'] : [];
        $accept = is_array($strategies['accept'] ?? null) ? $strategies['accept'] : [];
        $query = is_array($strategies['query'] ?? null) ? $strategies['query'] : [];

        return match ($resolver) {
            UriVersionResolver::class => new UriVersionResolver(
                $this->stringOrDefault($uri['parameter'] ?? null, 'version'),
                $this->stringOrDefault($uri['prefix'] ?? null, 'v'),
            ),
            HeaderVersionResolver::class => new HeaderVersionResolver(
                $this->stringOrDefault($header['name'] ?? null, 'Api-Version'),
            ),
            AcceptHeaderVersionResolver::class => new AcceptHeaderVersionResolver(
                $this->stringOrDefault($accept['vendor'] ?? null, 'myapp'),
            ),
            QueryStringVersionResolver::class => new QueryStringVersionResolver(
                $this->stringOrDefault($query['parameter'] ?? null, 'version'),
            ),
            default => $this->container->make($resolver),
        };
    }

    private function stringOrDefault(mixed $value, string $default): string
    {
        return is_string($value) && $value !== '' ? $value : $default;
    }

    /**
     * @param  array<string, mixed>  $strategies
     */
    private function maybeWrapWithAliases(VersionResolver $resolver, string $resolverClass, array $strategies): VersionResolver
    {
        $normalizedAliases = $this->normalizeAliases();

        if ($normalizedAliases === []) {
            return $resolver;
        }

        $rawExtractor = $this->buildRawExtractor($resolverClass, $strategies);

        return $rawExtractor !== null
            ? new AliasVersionResolver($resolver, $normalizedAliases, $rawExtractor)
            : $resolver;
    }

    /**
     * @return array<string, string>
     */
    private function normalizeAliases(): array
    {
        $aliases = $this->config['aliases'] ?? [];
        if (! is_array($aliases)) {
            return [];
        }

        $normalized = [];
        foreach ($aliases as $key => $value) {
            if (is_string($key) && $key !== '' && (is_string($value) || is_int($value))) {
                $normalized[$key] = (string) $value;
            }
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $strategies
     * @return (Closure(Request): ?string)|null
     */
    private function buildRawExtractor(string $resolverClass, array $strategies): ?Closure
    {
        $uri = is_array($strategies['uri'] ?? null) ? $strategies['uri'] : [];
        $header = is_array($strategies['header'] ?? null) ? $strategies['header'] : [];
        $query = is_array($strategies['query'] ?? null) ? $strategies['query'] : [];

        return match ($resolverClass) {
            UriVersionResolver::class => static function (Request $request) use ($uri): ?string {
                $parameter = is_string($uri['parameter'] ?? null) && $uri['parameter'] !== '' ? $uri['parameter'] : 'version';
                $value = $request->route()?->parameter($parameter);

                return is_string($value) ? $value : null;
            },
            HeaderVersionResolver::class => static function (Request $request) use ($header): ?string {
                $name = is_string($header['name'] ?? null) && $header['name'] !== '' ? $header['name'] : 'Api-Version';
                $value = $request->header($name);

                return is_string($value) ? $value : null;
            },
            QueryStringVersionResolver::class => static function (Request $request) use ($query): ?string {
                $parameter = is_string($query['parameter'] ?? null) && $query['parameter'] !== '' ? $query['parameter'] : 'version';
                $value = $request->query($parameter);

                return is_string($value) ? $value : null;
            },
            // AcceptHeaderVersionResolver uses structured MIME types — aliases are not applicable
            default => null,
        };
    }
}
