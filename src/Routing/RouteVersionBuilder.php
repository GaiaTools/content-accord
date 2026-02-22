<?php

namespace GaiaTools\ContentAccord\Routing;

use Closure;
use GaiaTools\ContentAccord\Http\Middleware\ApiVersionMetadata;
use GaiaTools\ContentAccord\Resolvers\Version\UriVersionResolver;
use Illuminate\Support\Facades\Route;

final class RouteVersionBuilder
{
    private ?string $prefix = null;

    private array $extraMiddleware = [];

    private ?bool $deprecated = null;

    private ?string $sunsetDate = null;

    private ?string $deprecationLink = null;

    private ?bool $fallback = null;

    public function __construct(private readonly string $version) {}

    public function prefix(string $prefix): static
    {
        $this->prefix = $prefix;

        return $this;
    }

    public function middleware(array|string $middleware): static
    {
        $this->extraMiddleware = array_merge(
            $this->extraMiddleware,
            is_array($middleware) ? $middleware : [$middleware]
        );

        return $this;
    }

    public function deprecated(bool $deprecated = true): static
    {
        $this->deprecated = $deprecated;

        return $this;
    }

    public function sunsetDate(string $date): static
    {
        $this->sunsetDate = $date;

        return $this;
    }

    public function deprecationLink(string $link): static
    {
        $this->deprecationLink = $link;

        return $this;
    }

    public function fallback(bool $enabled = true): static
    {
        $this->fallback = $enabled;

        return $this;
    }

    public function group(Closure $callback): void
    {
        $versionMiddleware = $this->buildVersionMiddleware();
        $allMiddleware = [$versionMiddleware, ...$this->extraMiddleware];
        $fullPrefix = $this->resolvePrefix();

        $registrar = Route::middleware($allMiddleware);

        if ($fullPrefix !== '') {
            $registrar = $registrar->prefix($fullPrefix);
        }

        $registrar->group($callback);
    }

    private function buildVersionMiddleware(): string
    {
        $parts = ['version='.$this->version];

        if ($this->deprecated !== null) {
            $parts[] = 'deprecated='.($this->deprecated ? 'true' : 'false');
        }

        if ($this->sunsetDate !== null) {
            $parts[] = 'sunset='.$this->sunsetDate;
        }

        if ($this->deprecationLink !== null) {
            $parts[] = 'link='.$this->deprecationLink;
        }

        if ($this->fallback !== null) {
            $parts[] = 'fallback='.($this->fallback ? 'true' : 'false');
        }

        return ApiVersionMetadata::class.':'.implode(',', $parts);
    }

    private function resolvePrefix(): string
    {
        if ($this->shouldAddVersionPrefix()) {
            $uriConfig = config('content-accord.versioning.strategies.uri', []);
            $versionPrefix = $uriConfig['prefix'] ?? 'v';
            $prefixPart = $this->prefix !== null && $this->prefix !== ''
                ? rtrim($this->prefix, '/').'/'
                : '';

            return $prefixPart.$versionPrefix.$this->version;
        }

        return $this->prefix ?? '';
    }

    private function shouldAddVersionPrefix(): bool
    {
        $resolverConfig = config('content-accord.versioning.resolver');

        if (is_array($resolverConfig)) {
            return in_array(UriVersionResolver::class, $resolverConfig, true);
        }

        if (is_string($resolverConfig) && $resolverConfig !== '') {
            return $resolverConfig === UriVersionResolver::class;
        }

        return false;
    }
}
