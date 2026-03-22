<?php

namespace GaiaTools\ContentAccord;

use GaiaTools\ContentAccord\Commands\ListApiVersionsCommand;
use GaiaTools\ContentAccord\Contracts\NegotiationDimension;
use GaiaTools\ContentAccord\Dimensions\VersioningDimension;
use GaiaTools\ContentAccord\Enums\MissingVersionStrategy;
use GaiaTools\ContentAccord\Http\Middleware\ApiVersionMetadata;
use GaiaTools\ContentAccord\Http\Middleware\DeprecationHeaders;
use GaiaTools\ContentAccord\Http\Middleware\EnforceSunset;
use GaiaTools\ContentAccord\Http\Middleware\NegotiateContext;
use GaiaTools\ContentAccord\Http\NegotiatedContext;
use GaiaTools\ContentAccord\Resolvers\Version\VersionResolverFactory;
use GaiaTools\ContentAccord\Routing\ApiVersionRegistrar;
use GaiaTools\ContentAccord\Routing\VersionedRouteCollection;
use GaiaTools\ContentAccord\ValueObjects\ApiVersion;
use Illuminate\Container\Container;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class ContentAccordServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/content-accord.php',
            'content-accord'
        );

        // Bind NegotiatedContext as scoped singleton
        $this->app->scoped(NegotiatedContext::class, function () {
            return new NegotiatedContext;
        });

        $this->app->bind(NegotiateContext::class, function (Container $app) {
            return new NegotiateContext(
                $this->resolveDimensions(),
                $app->make(NegotiatedContext::class)
            );
        });

        if ($this->usesVersioningDimension()) {
            $this->app->singleton('content-accord.resolver', function (Container $app) {
                return (new VersionResolverFactory($app, config()->array('content-accord.versioning')))->build();
            });

            $this->app->singleton(VersioningDimension::class, function (Container $app) {
                return $this->createVersioningDimension();
            });
        }
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/content-accord.php' => config_path('content-accord.php'),
        ], 'content-accord-config');

        ApiVersionRegistrar::register();

        $router = $this->app->make(Router::class);

        $this->registerMiddlewareAliases($router);
        $this->registerVersionedRoutes($router);

        if ($this->app->runningInConsole()) {
            $this->commands([
                ListApiVersionsCommand::class,
            ]);
        }
    }

    private function registerMiddlewareAliases(Router $router): void
    {
        $router->aliasMiddleware('content-accord.negotiate', NegotiateContext::class);
        $router->aliasMiddleware('content-accord.deprecate', DeprecationHeaders::class);
        $router->aliasMiddleware('content-accord.enforce-sunset', EnforceSunset::class);
        $router->aliasMiddleware('content-accord.version', ApiVersionMetadata::class);
    }

    private function registerVersionedRoutes(Router $router): void
    {
        if (! $this->usesVersioningDimension()) {
            return;
        }

        $config = config()->array('content-accord.versioning');
        $routes = $router->getRoutes();

        if ($routes instanceof VersionedRouteCollection) {
            return;
        }

        if (! $routes instanceof RouteCollection) {
            return;
        }

        /** @var Container $container */
        $container = $this->app;

        $router->setRoutes(
            VersionedRouteCollection::fromExisting(
                $routes,
                $config,
                $container
            )
        );
    }

    private function createVersioningDimension(): VersioningDimension
    {
        $config = config()->array('content-accord.versioning');
        /** @var Container $container */
        $container = $this->app;
        $resolver = (new VersionResolverFactory($container, $config))->build();

        $missingStrategy = MissingVersionStrategy::from(
            config()->string('content-accord.versioning.missing_strategy', 'reject')
        );
        $defaultVersionValue = config('content-accord.versioning.default_version');
        if ($defaultVersionValue === null || $defaultVersionValue === '') {
            $defaultVersion = null;
        } elseif (is_string($defaultVersionValue) || is_int($defaultVersionValue)) {
            $defaultVersion = ApiVersion::parse((string) $defaultVersionValue);
        } else {
            throw new InvalidArgumentException(sprintf(
                'Configuration value for key [content-accord.versioning.default_version] must be a string or null, %s given.',
                gettype($defaultVersionValue)
            ));
        }

        $versions = $config['versions'] ?? [];
        if (! is_array($versions)) {
            $versions = [];
        }
        $supportedVersions = array_map('intval', array_keys($versions));

        return new VersioningDimension(
            resolver: $resolver,
            missingStrategy: $missingStrategy,
            defaultVersion: $defaultVersion,
            supportedVersions: $supportedVersions
        );
    }

    private function usesVersioningDimension(): bool
    {
        $dimensions = config()->array('content-accord.dimensions', [VersioningDimension::class]);

        foreach ($dimensions as $dimension) {
            if ($dimension instanceof VersioningDimension || $dimension === VersioningDimension::class) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return NegotiationDimension[]
     */
    private function resolveDimensions(): array
    {
        $dimensions = config()->array('content-accord.dimensions', [VersioningDimension::class]);

        $resolved = [];

        foreach ($dimensions as $dimension) {
            if ($dimension instanceof NegotiationDimension) {
                $resolved[] = $dimension;

                continue;
            }

            if (! is_string($dimension) || $dimension === '') {
                throw new InvalidArgumentException('Configured dimensions must be class names or instances.');
            }

            $instance = $this->app->make($dimension);

            if (! $instance instanceof NegotiationDimension) {
                throw new InvalidArgumentException('Configured dimension must implement NegotiationDimension.');
            }

            $resolved[] = $instance;
        }

        return $resolved;
    }
}
