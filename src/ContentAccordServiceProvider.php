<?php

namespace GaiaTools\ContentAccord;

use GaiaTools\ContentAccord\Commands\ListApiVersionsCommand;
use GaiaTools\ContentAccord\Contracts\NegotiationDimension;
use GaiaTools\ContentAccord\Dimensions\VersioningDimension;
use GaiaTools\ContentAccord\Enums\MissingVersionStrategy;
use GaiaTools\ContentAccord\Http\Middleware\ApiVersionMetadata;
use GaiaTools\ContentAccord\Http\Middleware\DeprecationHeaders;
use GaiaTools\ContentAccord\Http\Middleware\NegotiateContext;
use GaiaTools\ContentAccord\Http\NegotiatedContext;
use GaiaTools\ContentAccord\Resolvers\Version\VersionResolverFactory;
use GaiaTools\ContentAccord\Routing\ApiVersionRegistrar;
use GaiaTools\ContentAccord\ValueObjects\ApiVersion;
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

        $this->app->bind(NegotiateContext::class, function ($app) {
            return new NegotiateContext(
                $this->resolveDimensions(),
                $app->make(NegotiatedContext::class)
            );
        });

        if ($this->usesVersioningDimension()) {
            $this->app->singleton('content-accord.resolver', function ($app) {
                return (new VersionResolverFactory($app, config('content-accord.versioning')))->build();
            });

            $this->app->singleton(VersioningDimension::class, function ($app) {
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
        $router->aliasMiddleware('content-accord.version', ApiVersionMetadata::class);
    }

    private function registerVersionedRoutes(Router $router): void
    {
        if (! $this->usesVersioningDimension()) {
            return;
        }

        $config = config('content-accord.versioning');
        $routes = $router->getRoutes();

        if ($routes instanceof \GaiaTools\ContentAccord\Routing\VersionedRouteCollection) {
            return;
        }

        $router->setRoutes(
            \GaiaTools\ContentAccord\Routing\VersionedRouteCollection::fromExisting(
                $routes,
                $config,
                $this->app
            )
        );
    }

    private function createVersioningDimension(): VersioningDimension
    {
        $config = config('content-accord.versioning');
        $resolver = (new VersionResolverFactory($this->app, $config))->build();

        $missingStrategy = MissingVersionStrategy::from($config['missing_strategy']);
        $defaultVersion = $config['default_version']
            ? ApiVersion::parse($config['default_version'])
            : null;

        $supportedVersions = array_map('intval', array_keys($config['versions']));

        return new VersioningDimension(
            resolver: $resolver,
            missingStrategy: $missingStrategy,
            defaultVersion: $defaultVersion,
            supportedVersions: $supportedVersions
        );
    }

    private function usesVersioningDimension(): bool
    {
        $dimensions = config('content-accord.dimensions', [VersioningDimension::class]);

        if (! is_array($dimensions)) {
            return false;
        }

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
        $dimensions = config('content-accord.dimensions', [VersioningDimension::class]);

        if (! is_array($dimensions)) {
            throw new InvalidArgumentException('Configured dimensions must be an array.');
        }

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
