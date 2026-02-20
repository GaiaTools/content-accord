<?php

namespace GaiaTools\ContentAccord\Routing;

use Closure;
use GaiaTools\ContentAccord\Attributes\ApiVersion as ApiVersionAttribute;
use GaiaTools\ContentAccord\Attributes\MapToVersion;
use GaiaTools\ContentAccord\Http\Middleware\DeprecationHeaders;
use GaiaTools\ContentAccord\Http\Middleware\NegotiateContext;
use GaiaTools\ContentAccord\ValueObjects\ApiVersion;
use Illuminate\Routing\Router;
use ReflectionClass;
use Throwable;

class PendingVersionedRouteGroup
{
    private string $prefix = '';

    private array $middleware = [];

    private ?bool $isDeprecated = null;

    private ?string $sunsetDate = null;

    private ?string $deprecationLink = null;

    private ?bool $fallbackEnabled = null;

    public function __construct(
        private readonly Router $router,
        private readonly string $version,
        private readonly array $config,
    ) {
    }

    public function prefix(string $prefix): self
    {
        $this->prefix = $prefix;

        return $this;
    }

    public function middleware(array|string $middleware): self
    {
        $this->middleware = array_merge(
            $this->middleware,
            is_array($middleware) ? $middleware : [$middleware]
        );

        return $this;
    }

    public function deprecated(bool $deprecated = true): self
    {
        $this->isDeprecated = $deprecated;

        return $this;
    }

    public function sunsetDate(string $date): self
    {
        $this->sunsetDate = $date;

        return $this;
    }

    public function deprecationLink(string $link): self
    {
        $this->deprecationLink = $link;

        return $this;
    }

    public function fallbackToVersion(bool $enabled = true): self
    {
        $this->fallbackEnabled = $enabled;

        return $this;
    }

    public function group(Closure $routes): void
    {
        $existingRoutes = $this->router->getRoutes()->getRoutes();
        $existingIds = array_flip(array_map('spl_object_id', $existingRoutes));

        $attributes = $this->buildGroupAttributes();

        $this->router->group($attributes, $routes);

        $this->applyAttributeOverrides($existingIds);
    }

    private function buildGroupAttributes(): array
    {
        $strategy = $this->config['strategy'] ?? 'uri';
        $versions = $this->config['versions'] ?? [];
        $versionMetadata = $versions[$this->version] ?? [];
        $attributes = [];

        // Build prefix based on strategy
        if ($strategy === 'uri') {
            $versionPrefix = ($this->config['strategies']['uri']['prefix'] ?? 'v') . $this->version;
            $attributes['prefix'] = $this->prefix
                ? "{$this->prefix}/{$versionPrefix}"
                : $versionPrefix;
        } else {
            // For header/accept strategies, don't modify the prefix
            if ($this->prefix) {
                $attributes['prefix'] = $this->prefix;
            }
        }

        $deprecated = $this->isDeprecated;

        if ($deprecated === null) {
            $deprecated = (bool) ($versionMetadata['deprecated'] ?? false);
        }

        // Add middleware
        $middleware = $this->middleware;
        $middleware[] = NegotiateContext::class;

        if ($deprecated || $this->sunsetDate || $this->deprecationLink || ($versionMetadata['sunset'] ?? null) || ($versionMetadata['deprecation_link'] ?? null)) {
            $middleware[] = DeprecationHeaders::class;
        }

        $attributes['middleware'] = array_values(array_unique($middleware));

        // Add deprecation metadata
        if ($deprecated) {
            $attributes['deprecated'] = true;

            $sunsetDate = $this->sunsetDate ?? ($versionMetadata['sunset'] ?? null);
            if ($sunsetDate) {
                $attributes['sunset'] = $sunsetDate;
            }

            $deprecationLink = $this->deprecationLink ?? ($versionMetadata['deprecation_link'] ?? null);
            if ($deprecationLink) {
                $attributes['deprecation_link'] = $deprecationLink;
            }
        }

        // Add version metadata
        $attributes['api_version'] = $this->version;

        // Add fallback setting if explicitly set
        $attributes['fallback_enabled'] = $this->fallbackEnabled ?? (bool) ($this->config['fallback'] ?? false);

        return $attributes;
    }

    private function applyAttributeOverrides(array $existingIds): void
    {
        $routes = $this->router->getRoutes()->getRoutes();

        foreach ($routes as $route) {
            if (isset($existingIds[spl_object_id($route)])) {
                continue;
            }

            $action = $route->getAction();
            $controller = $action['controller'] ?? null;

            if (! is_string($controller)) {
                continue;
            }

            [$class, $method] = $this->parseControllerAction($controller);

            if (! class_exists($class)) {
                continue;
            }

            $resolvedVersion = $this->resolveAttributeVersion($class, $method);

            if ($resolvedVersion) {
                $action['api_version'] = $resolvedVersion;
            }

            $route->setAction($action);
        }
    }

    private function resolveAttributeVersion(string $class, string $method): ?string
    {
        $classReflection = new ReflectionClass($class);
        $classVersion = $this->firstAttributeVersion($classReflection->getAttributes(ApiVersionAttribute::class));

        $methodVersion = null;
        if ($classReflection->hasMethod($method)) {
            $methodReflection = $classReflection->getMethod($method);
            $methodVersion = $this->firstAttributeVersion($methodReflection->getAttributes(MapToVersion::class));

            if ($methodVersion === null) {
                $methodVersion = $this->firstAttributeVersion($methodReflection->getAttributes(ApiVersionAttribute::class));
            }
        }

        $resolved = $methodVersion ?? $classVersion;

        if ($resolved) {
            $this->warnOnVersionMismatch($resolved, $class, $method);
        }

        return $resolved;
    }

    private function firstAttributeVersion(array $attributes): ?string
    {
        if ($attributes === []) {
            return null;
        }

        $instance = $attributes[0]->newInstance();

        return $instance->version ?? null;
    }

    private function parseControllerAction(string $controller): array
    {
        if (str_contains($controller, '@')) {
            return explode('@', $controller, 2);
        }

        return [$controller, '__invoke'];
    }

    private function warnOnVersionMismatch(string $resolvedVersion, string $class, string $method): void
    {
        try {
            $groupVersion = ApiVersion::parse($this->version);
            $attributeVersion = ApiVersion::parse($resolvedVersion);
        } catch (Throwable) {
            return;
        }

        if ($groupVersion->major === $attributeVersion->major) {
            return;
        }

        if (! app()->bound('log')) {
            return;
        }

        if (! app()->environment(['local', 'testing', 'development'])) {
            return;
        }

        app('log')->warning('ContentAccord: Attribute version mismatch detected.', [
            'group_version' => $this->version,
            'attribute_version' => $resolvedVersion,
            'controller' => $class,
            'method' => $method,
        ]);
    }
}
