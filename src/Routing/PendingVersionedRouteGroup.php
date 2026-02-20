<?php

namespace GaiaTools\ContentAccord\Routing;

use Closure;
use Illuminate\Routing\Router;

class PendingVersionedRouteGroup
{
    private string $prefix = '';

    private array $middleware = [];

    private bool $isDeprecated = false;

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
        $attributes = $this->buildGroupAttributes();

        $this->router->group($attributes, $routes);
    }

    private function buildGroupAttributes(): array
    {
        $strategy = $this->config['strategy'] ?? 'uri';
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

        // Add middleware
        if (! empty($this->middleware)) {
            $attributes['middleware'] = $this->middleware;
        }

        // Add deprecation metadata
        if ($this->isDeprecated) {
            $attributes['deprecated'] = true;

            if ($this->sunsetDate) {
                $attributes['sunset'] = $this->sunsetDate;
            }

            if ($this->deprecationLink) {
                $attributes['deprecation_link'] = $this->deprecationLink;
            }
        }

        // Add version metadata
        $attributes['api_version'] = $this->version;

        // Add fallback setting if explicitly set
        if ($this->fallbackEnabled !== null) {
            $attributes['fallback_enabled'] = $this->fallbackEnabled;
        }

        return $attributes;
    }
}
