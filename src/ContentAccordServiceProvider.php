<?php

namespace GaiaTools\ContentAccord;

use GaiaTools\ContentAccord\Contracts\ContextResolver;
use GaiaTools\ContentAccord\Dimensions\VersioningDimension;
use GaiaTools\ContentAccord\Enums\MissingVersionStrategy;
use GaiaTools\ContentAccord\Http\NegotiatedContext;
use GaiaTools\ContentAccord\Resolvers\ChainedResolver;
use GaiaTools\ContentAccord\Resolvers\Version\AcceptHeaderVersionResolver;
use GaiaTools\ContentAccord\Resolvers\Version\HeaderVersionResolver;
use GaiaTools\ContentAccord\Resolvers\Version\UriVersionResolver;
use GaiaTools\ContentAccord\Routing\PendingVersionedRouteGroup;
use GaiaTools\ContentAccord\ValueObjects\ApiVersion;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

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

        // Register Route::apiVersion() macro
        Router::macro('apiVersion', function (string $version) {
            return new PendingVersionedRouteGroup(
                app(Router::class),
                $version,
                config('content-accord.versioning')
            );
        });
    }

    private function createResolver(): ContextResolver
    {
        $config = config('content-accord.versioning');
        $chain = $config['chain'];

        if ($chain !== null && is_array($chain)) {
            $resolvers = [];
            foreach ($chain as $strategy) {
                $resolvers[] = $this->createResolverForStrategy($strategy, $config);
            }

            return new ChainedResolver($resolvers);
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
}
