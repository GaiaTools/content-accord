<?php

namespace GaiaTools\ContentAccord;

use GaiaTools\ContentAccord\Commands\ListApiVersionsCommand;
use GaiaTools\ContentAccord\Contracts\ContextResolver;
use GaiaTools\ContentAccord\Contracts\NegotiationDimension;
use GaiaTools\ContentAccord\Dimensions\VersioningDimension;
use GaiaTools\ContentAccord\Enums\MissingVersionStrategy;
use GaiaTools\ContentAccord\Http\Middleware\DeprecationHeaders;
use GaiaTools\ContentAccord\Http\Middleware\NegotiateContext;
use GaiaTools\ContentAccord\Http\NegotiatedContext;
use GaiaTools\ContentAccord\Resolvers\ChainedResolver;
use GaiaTools\ContentAccord\Resolvers\Version\AcceptHeaderVersionResolver;
use GaiaTools\ContentAccord\Resolvers\Version\HeaderVersionResolver;
use GaiaTools\ContentAccord\Resolvers\Version\UriVersionResolver;
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
            __DIR__ . '/../config/content-accord.php',
            'content-accord'
        );

        // Bind NegotiatedContext as scoped singleton
        $this->app->scoped(NegotiatedContext::class, function () {
            return new NegotiatedContext();
        });

        $this->app->bind(NegotiateContext::class, function ($app) {
            return new NegotiateContext(
                $this->resolveDimensions(),
                $app->make(NegotiatedContext::class)
            );
        });

        // Register resolver factory
        $this->app->singleton('content-accord.resolver', function ($app) {
            return $this->createResolver();
        });

        // Register versioning dimension
        $this->app->singleton(VersioningDimension::class, function ($app) {
            return $this->createVersioningDimension();
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/content-accord.php' => config_path('content-accord.php'),
        ], 'content-accord-config');

        $router = $this->app->make(Router::class);

        $this->registerMiddlewareAliases($router);
        $this->registerApiVersionMacro($router);

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
    }

    private function registerApiVersionMacro(Router $router): void
    {
        $config = config('content-accord.versioning');

        (new ApiVersionRegistrar($router, $config, $this->app))->register();
    }

    private function createResolver(): ContextResolver
    {
        $config = config('content-accord.versioning');
        $resolverConfig = $config['resolver'] ?? null;

        if (is_array($resolverConfig)) {
            $resolvers = array_map(fn ($resolver) => $this->resolveResolver($resolver, $config), $resolverConfig);

            return new ChainedResolver($resolvers);
        }

        if (is_string($resolverConfig) && $resolverConfig !== '') {
            return $this->resolveResolver($resolverConfig, $config);
        }

        return $this->createResolverForStrategy($config['strategy'], $config);
    }

    private function createResolverForStrategy(string $strategy, array $config): ContextResolver
    {
        return match ($strategy) {
            'uri' => new UriVersionResolver($config['strategies']['uri']['parameter']),
            'header' => new HeaderVersionResolver($config['strategies']['header']['name']),
            'accept' => new AcceptHeaderVersionResolver($config['strategies']['accept']['vendor']),
            default => new UriVersionResolver($config['strategies']['uri']['parameter']),
        };
    }

    private function resolveResolver(mixed $resolver, array $config): ContextResolver
    {
        if ($resolver instanceof ContextResolver) {
            return $resolver;
        }

        if (! is_string($resolver) || $resolver === '') {
            throw new InvalidArgumentException('Configured resolver must be a class name, binding, or ContextResolver instance.');
        }

        $resolved = match ($resolver) {
            UriVersionResolver::class => new UriVersionResolver($config['strategies']['uri']['parameter'] ?? 'version'),
            HeaderVersionResolver::class => new HeaderVersionResolver($config['strategies']['header']['name'] ?? 'Api-Version'),
            AcceptHeaderVersionResolver::class => new AcceptHeaderVersionResolver($config['strategies']['accept']['vendor'] ?? 'myapp'),
            default => $this->app->make($resolver),
        };

        if (! $resolved instanceof ContextResolver) {
            throw new InvalidArgumentException('Configured resolver must implement ContextResolver.');
        }

        return $resolved;
    }

    private function createVersioningDimension(): VersioningDimension
    {
        $config = config('content-accord.versioning');
        $resolver = app('content-accord.resolver');

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
