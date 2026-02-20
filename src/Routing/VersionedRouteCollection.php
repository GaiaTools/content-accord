<?php

namespace GaiaTools\ContentAccord\Routing;

use GaiaTools\ContentAccord\Contracts\ContextResolver;
use GaiaTools\ContentAccord\Enums\MissingVersionStrategy;
use GaiaTools\ContentAccord\Exceptions\MissingVersionException;
use GaiaTools\ContentAccord\Exceptions\UnsupportedVersionException;
use GaiaTools\ContentAccord\Resolvers\ChainedResolver;
use GaiaTools\ContentAccord\Resolvers\Version\AcceptHeaderVersionResolver;
use GaiaTools\ContentAccord\Resolvers\Version\HeaderVersionResolver;
use GaiaTools\ContentAccord\Resolvers\Version\UriVersionResolver;
use GaiaTools\ContentAccord\ValueObjects\ApiVersion;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollection;
use InvalidArgumentException;

class VersionedRouteCollection extends RouteCollection
{
    private ?object $resolver = null;

    public function __construct(
        private array $config,
        private Container $container,
    ) {
    }

    public static function fromExisting(RouteCollection $routes, array $config, Container $container): self
    {
        $collection = new self($config, $container);

        foreach ($routes->getRoutes() as $route) {
            $collection->add($route);
        }

        return $collection;
    }

    protected function addToCollections($route)
    {
        $methods = $route->methods();
        $domainAndUri = $route->getDomain() . $route->uri();
        $versionKey = $this->versionKey($route);

        foreach ($methods as $method) {
            $this->routes[$method][$domainAndUri . $versionKey] = $route;
        }

        $this->allRoutes[implode('|', $methods) . $domainAndUri . $versionKey] = $route;
    }

    protected function matchAgainstRoutes(array $routes, $request, $includingMethod = true)
    {
        $fallbackRoute = null;
        $matched = [];

        foreach ($routes as $route) {
            if ($route->matches($request, $includingMethod)) {
                if ($route->isFallback) {
                    $fallbackRoute ??= $route;

                    continue;
                }

                $matched[] = $route;
            }
        }

        if ($matched === []) {
            return $fallbackRoute;
        }

        if (count($matched) === 1) {
            $route = $matched[0];

            if ($route->getAction('api_version')) {
                return $this->selectVersionedRoute($matched, $request) ?? $fallbackRoute;
            }

            return $route;
        }

        return $this->selectVersionedRoute($matched, $request) ?? $fallbackRoute;
    }

    /**
     * @param  Route[]  $routes
     */
    private function selectVersionedRoute(array $routes, Request $request): ?Route
    {
        $versionedRoutes = array_values(array_filter($routes, function (Route $route) {
            return (bool) $route->getAction('api_version');
        }));

        if ($versionedRoutes === []) {
            return $routes[0] ?? null;
        }

        $requestedVersion = $this->resolveRequestedVersion($request);
        $this->ensureSupportedVersion($requestedVersion);

        $candidates = $this->buildVersionCandidates($versionedRoutes);

        $exactMatch = $this->findBestMatch($candidates, $requestedVersion->major);
        if ($exactMatch) {
            return $exactMatch['route'];
        }

        return $this->findFallbackRoute($candidates, $requestedVersion->major);
    }

    /**
     * @param  array<int, array{route: Route, version: ApiVersion, fallback: bool}>  $candidates
     */
    private function findBestMatch(array $candidates, int $major): ?array
    {
        $matches = array_values(array_filter($candidates, fn ($candidate) => $candidate['version']->major === $major));

        if ($matches === []) {
            return null;
        }

        usort($matches, function ($a, $b) {
            return $b['version']->minor <=> $a['version']->minor;
        });

        return $matches[0];
    }

    /**
     * @param  array<int, array{route: Route, version: ApiVersion, fallback: bool}>  $candidates
     */
    private function findFallbackRoute(array $candidates, int $requestedMajor): ?Route
    {
        $fallbackCandidates = array_values(array_filter($candidates, function ($candidate) use ($requestedMajor) {
            return $candidate['fallback'] && $candidate['version']->major < $requestedMajor;
        }));

        if ($fallbackCandidates === []) {
            return null;
        }

        usort($fallbackCandidates, function ($a, $b) {
            if ($a['version']->major === $b['version']->major) {
                return $b['version']->minor <=> $a['version']->minor;
            }

            return $b['version']->major <=> $a['version']->major;
        });

        return $fallbackCandidates[0]['route'];
    }

    /**
     * @param  Route[]  $routes
     * @return array<int, array{route: Route, version: ApiVersion, fallback: bool}>
     */
    private function buildVersionCandidates(array $routes): array
    {
        $defaultFallback = (bool) ($this->config['fallback'] ?? false);

        $candidates = [];

        foreach ($routes as $route) {
            $versionString = $route->getAction('api_version');

            if (! is_string($versionString) || $versionString === '') {
                continue;
            }

            $candidates[] = [
                'route' => $route,
                'version' => ApiVersion::parse($versionString),
                'fallback' => (bool) ($route->getAction('fallback_enabled') ?? $defaultFallback),
            ];
        }

        return $candidates;
    }

    private function resolveRequestedVersion(Request $request): ApiVersion
    {
        $resolved = $this->resolver()->resolve($request);

        if ($resolved instanceof ApiVersion) {
            return $resolved;
        }

        if ($resolved !== null) {
            throw UnsupportedVersionException::forVersion(new ApiVersion(0), $this->supportedVersions());
        }

        return $this->resolveMissingVersion();
    }

    private function resolveMissingVersion(): ApiVersion
    {
        $strategy = MissingVersionStrategy::from($this->config['missing_strategy'] ?? 'reject');
        $supported = $this->supportedVersions();

        return match ($strategy) {
            MissingVersionStrategy::Reject => throw new MissingVersionException(),
            MissingVersionStrategy::DefaultVersion => $this->defaultVersion()
                ?? throw new MissingVersionException('No default version configured'),
            MissingVersionStrategy::LatestVersion => $this->latestVersion($supported),
            MissingVersionStrategy::Require => throw new MissingVersionException(
                $this->buildRequirementMessage($supported)
            ),
        };
    }

    private function latestVersion(array $supported): ApiVersion
    {
        if ($supported === []) {
            throw new MissingVersionException('No supported versions configured');
        }

        return new ApiVersion(max($supported));
    }

    private function buildRequirementMessage(array $supported): string
    {
        if ($supported === []) {
            return 'API version is required.';
        }

        $list = implode(', ', array_map(fn ($v) => "v{$v}", $supported));

        return "API version is required. Supported versions: {$list}";
    }

    private function ensureSupportedVersion(ApiVersion $version): void
    {
        $supported = $this->supportedVersions();

        if ($supported === []) {
            return;
        }

        if (! in_array($version->major, $supported, true)) {
            throw UnsupportedVersionException::forVersion($version, $supported);
        }
    }

    /**
     * @return int[]
     */
    private function supportedVersions(): array
    {
        $versions = $this->config['versions'] ?? [];

        return array_map('intval', array_keys($versions));
    }

    private function defaultVersion(): ?ApiVersion
    {
        $defaultVersion = $this->config['default_version'] ?? null;

        if (! is_string($defaultVersion) || $defaultVersion === '') {
            return null;
        }

        return ApiVersion::parse($defaultVersion);
    }

    private function resolver(): object
    {
        if ($this->resolver) {
            return $this->resolver;
        }

        if ($this->container->bound('content-accord.resolver')) {
            $this->resolver = $this->container->make('content-accord.resolver');

            return $this->resolver;
        }

        $this->resolver = $this->buildResolverFromConfig();

        return $this->resolver;
    }

    private function buildResolverFromConfig(): ContextResolver
    {
        $resolverConfig = $this->config['resolver'] ?? null;

        if (is_array($resolverConfig)) {
            $resolvers = array_map(fn ($resolver) => $this->resolveResolver($resolver), $resolverConfig);

            return new ChainedResolver($resolvers);
        }

        if (is_string($resolverConfig) && $resolverConfig !== '') {
            return $this->resolveResolver($resolverConfig);
        }

        $strategy = $this->config['strategy'] ?? 'uri';
        $strategyConfig = $this->config['strategies'] ?? [];

        return match ($strategy) {
            'header' => new HeaderVersionResolver($strategyConfig['header']['name'] ?? 'Api-Version'),
            'accept' => new AcceptHeaderVersionResolver($strategyConfig['accept']['vendor'] ?? 'myapp'),
            default => new UriVersionResolver($strategyConfig['uri']['parameter'] ?? 'version'),
        };
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
            UriVersionResolver::class => new UriVersionResolver($this->config['strategies']['uri']['parameter'] ?? 'version'),
            HeaderVersionResolver::class => new HeaderVersionResolver($this->config['strategies']['header']['name'] ?? 'Api-Version'),
            AcceptHeaderVersionResolver::class => new AcceptHeaderVersionResolver($this->config['strategies']['accept']['vendor'] ?? 'myapp'),
            default => $this->container->make($resolver),
        };

        if (! $resolved instanceof ContextResolver) {
            throw new InvalidArgumentException('Configured resolver must implement ContextResolver.');
        }

        return $resolved;
    }

    private function versionKey(Route $route): string
    {
        $version = $route->getAction('api_version');

        if (! is_string($version) || $version === '') {
            return '';
        }

        return "|version:{$version}";
    }
}
