<?php

namespace GaiaTools\ContentAccord\Routing;

use Illuminate\Container\Container;
use Illuminate\Routing\Router;

final readonly class ApiVersionRegistrar
{
    public function __construct(
        private Router $router,
        private array $config,
        private Container $container,
    ) {
    }

    public function register(): void
    {
        $this->ensureVersionedRouteCollection();

        $this->router->macro('apiVersion', function (string $version) {
            return new PendingVersionedRouteGroup(
                $this,
                $version,
                config('content-accord.versioning')
            );
        });
    }

    private function ensureVersionedRouteCollection(): void
    {
        $routes = $this->router->getRoutes();

        if ($routes instanceof VersionedRouteCollection) {
            return;
        }

        $this->router->setRoutes(
            VersionedRouteCollection::fromExisting($routes, $this->config, $this->container)
        );
    }
}
