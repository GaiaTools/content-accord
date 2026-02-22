<?php

namespace GaiaTools\ContentAccord\Testing;

use GaiaTools\ContentAccord\Resolvers\Version\AcceptHeaderVersionResolver;
use GaiaTools\ContentAccord\Resolvers\Version\HeaderVersionResolver;
use GaiaTools\ContentAccord\Routing\RouteVersionMetadata;
use GaiaTools\ContentAccord\ValueObjects\ApiVersion;
use Illuminate\Routing\Router;
use Illuminate\Testing\TestResponse;

class ApiVersionRequestBuilder
{
    public function __construct(
        private object $testCase,
        private string $version,
    ) {}

    public function get(string $uri, array $headers = []): TestResponse
    {
        [$uri, $headers] = $this->resolveUriAndHeaders($uri, $headers, 'GET');

        return $this->testCase->get($uri, $headers);
    }

    public function post(string $uri, array $data = [], array $headers = []): TestResponse
    {
        [$uri, $headers] = $this->resolveUriAndHeaders($uri, $headers, 'POST');

        return $this->testCase->post($uri, $data, $headers);
    }

    public function put(string $uri, array $data = [], array $headers = []): TestResponse
    {
        [$uri, $headers] = $this->resolveUriAndHeaders($uri, $headers, 'PUT');

        return $this->testCase->put($uri, $data, $headers);
    }

    public function patch(string $uri, array $data = [], array $headers = []): TestResponse
    {
        [$uri, $headers] = $this->resolveUriAndHeaders($uri, $headers, 'PATCH');

        return $this->testCase->patch($uri, $data, $headers);
    }

    public function delete(string $uri, array $data = [], array $headers = []): TestResponse
    {
        [$uri, $headers] = $this->resolveUriAndHeaders($uri, $headers, 'DELETE');

        return $this->testCase->delete($uri, $data, $headers);
    }

    public function json(string $method, string $uri, array $data = [], array $headers = [], int $options = 0): TestResponse
    {
        [$uri, $headers] = $this->resolveUriAndHeaders($uri, $headers, strtoupper($method));

        return $this->testCase->json($method, $uri, $data, $headers, $options);
    }

    private function resolveUriAndHeaders(string $uri, array $headers, string $method): array
    {
        $resolverConfig = config('content-accord.versioning.resolver');
        $strategies = config('content-accord.versioning.strategies', []);

        $firstResolver = is_array($resolverConfig) ? ($resolverConfig[0] ?? null) : $resolverConfig;

        return match ($firstResolver) {
            HeaderVersionResolver::class => [$uri, $this->withHeaderVersion($headers, $strategies)],
            AcceptHeaderVersionResolver::class => [$uri, $this->withAcceptVersion($headers, $strategies)],
            default => [$this->withUriVersion($uri, $method, $strategies), $headers],
        };
    }

    private function withHeaderVersion(array $headers, array $strategies): array
    {
        $headerName = $strategies['header']['name'] ?? 'Api-Version';
        $headers[$headerName] = $this->version;

        return $headers;
    }

    private function withAcceptVersion(array $headers, array $strategies): array
    {
        $vendor = $strategies['accept']['vendor'] ?? 'myapp';
        $versionHeader = "application/vnd.{$vendor}.v{$this->version}+json";

        if (isset($headers['Accept']) && is_string($headers['Accept']) && $headers['Accept'] !== '') {
            $headers['Accept'] .= ", {$versionHeader}";
        } else {
            $headers['Accept'] = $versionHeader;
        }

        return $headers;
    }

    private function withUriVersion(string $uri, string $method, array $strategies): string
    {
        $router = app(Router::class);
        $prefix = $strategies['uri']['prefix'] ?? 'v';

        $requestVersion = ApiVersion::parse($this->version);
        $targetMajor = $requestVersion->major;
        $normalizedUri = ltrim($uri, '/');

        foreach ($router->getRoutes()->getRoutes() as $route) {
            if (! in_array($method, $route->methods(), true)) {
                continue;
            }

            $metadata = RouteVersionMetadata::resolve($route, config('content-accord.versioning', []));
            $routeVersion = $metadata['version'] ?? null;
            if (! is_string($routeVersion) || $routeVersion === '') {
                continue;
            }

            $parsed = ApiVersion::parse($routeVersion);
            if ($parsed->major !== $targetMajor) {
                continue;
            }

            $routeUri = $route->uri();
            $normalizedRouteUri = $this->stripVersionSegment($routeUri, $prefix, $parsed->major);

            if ($normalizedRouteUri === $normalizedUri) {
                return '/'.$routeUri;
            }
        }

        return $this->injectVersionSegment($normalizedUri, $prefix);
    }

    private function stripVersionSegment(string $uri, string $prefix, int $major): string
    {
        $segments = array_values(array_filter(explode('/', trim($uri, '/')), static fn ($segment) => $segment !== ''));
        $needle = $prefix.$major;

        foreach ($segments as $index => $segment) {
            if ($segment === $needle) {
                unset($segments[$index]);
                break;
            }
        }

        return implode('/', array_values($segments));
    }

    private function injectVersionSegment(string $uri, string $prefix): string
    {
        $versionSegment = $prefix.$this->version;

        if ($uri === '') {
            return '/'.$versionSegment;
        }

        return '/'.$versionSegment.'/'.$uri;
    }
}
