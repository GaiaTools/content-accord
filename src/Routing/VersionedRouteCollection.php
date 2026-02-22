<?php

namespace GaiaTools\ContentAccord\Routing;

use GaiaTools\ContentAccord\Contracts\ContextResolver;
use GaiaTools\ContentAccord\Enums\MissingVersionStrategy;
use GaiaTools\ContentAccord\Exceptions\MissingVersionException;
use GaiaTools\ContentAccord\Exceptions\UnsupportedVersionException;
use GaiaTools\ContentAccord\Resolvers\Version\VersionResolverFactory;
use GaiaTools\ContentAccord\ValueObjects\ApiVersion;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollection;

class VersionedRouteCollection extends RouteCollection
{
    private ?object $resolver = null;

    public function __construct(
        private array $config,
        private Container $container,
    ) {}

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
        $domainAndUri = $route->getDomain().$route->uri();
        $versionKey = $this->versionKey($route);

        foreach ($methods as $method) {
            $this->routes[$method][$domainAndUri.$versionKey] = $route;
        }

        $this->allRoutes[implode('|', $methods).$domainAndUri.$versionKey] = $route;
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
        $candidates = [];

        foreach ($routes as $route) {
            $metadata = RouteVersionMetadata::resolve($route, $this->config);
            $versionString = $metadata['version'] ?? null;

            if (! is_string($versionString) || $versionString === '') {
                continue;
            }

            $candidates[] = [
                'route' => $route,
                'version' => ApiVersion::parse($versionString),
                'fallback' => (bool) ($metadata['fallback'] ?? false),
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
            MissingVersionStrategy::Reject => throw new MissingVersionException,
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

        if ($this->config === [] && $this->container->bound('content-accord.resolver')) {
            $this->resolver = $this->container->make('content-accord.resolver');

            return $this->resolver;
        }

        $this->resolver = $this->buildResolverFromConfig();

        return $this->resolver;
    }

    private function buildResolverFromConfig(): ContextResolver
    {
        return (new VersionResolverFactory($this->container, $this->config))->build();
    }

    private function versionKey(Route $route): string
    {
        $version = $route->getAction('api_version');

        if (! is_string($version) || $version === '') {
            $metadata = RouteVersionMetadata::resolve($route, $this->config);
            $version = $metadata['version'] ?? null;

            if (is_string($version) && $version !== '') {
                $action = $route->getAction();
                $action['api_version'] = $version;

                if (array_key_exists('deprecated', $metadata)) {
                    $action['deprecated'] = $metadata['deprecated'];
                }

                if (array_key_exists('sunset', $metadata)) {
                    $action['sunset'] = $metadata['sunset'];
                }

                if (array_key_exists('deprecation_link', $metadata)) {
                    $action['deprecation_link'] = $metadata['deprecation_link'];
                }

                if (array_key_exists('fallback', $metadata)) {
                    $action['fallback_enabled'] = $metadata['fallback'];
                }

                $route->setAction($action);
            }
        }

        if (! is_string($version) || $version === '') {
            return '';
        }

        return "|version:{$version}";
    }
}
