<?php

use GaiaTools\ContentAccord\ContentAccordServiceProvider;
use GaiaTools\ContentAccord\Contracts\ContextResolver;
use GaiaTools\ContentAccord\Contracts\NegotiationDimension;
use GaiaTools\ContentAccord\Dimensions\VersioningDimension;
use GaiaTools\ContentAccord\Http\Middleware\NegotiateContext;
use GaiaTools\ContentAccord\Resolvers\ChainedResolver;
use GaiaTools\ContentAccord\Resolvers\Version\HeaderVersionResolver;
use GaiaTools\ContentAccord\Resolvers\Version\UriVersionResolver;
use GaiaTools\ContentAccord\Routing\VersionedRouteCollection;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;

test('service provider swaps in versioned route collection', function () {
    $provider = app()->getProvider(ContentAccordServiceProvider::class);

    $provider->boot();

    $router = app(Router::class);

    expect($router->getRoutes())->toBeInstanceOf(VersionedRouteCollection::class);
});

test('service provider builds chained resolver from configuration', function () {
    config([
        'content-accord.versioning.resolver' => [
            UriVersionResolver::class,
            HeaderVersionResolver::class,
        ],
        'content-accord.versioning.strategies.uri.parameter' => 'version',
        'content-accord.versioning.strategies.header.name' => 'Api-Version',
    ]);

    app()->forgetInstance('content-accord.resolver');

    $resolver = app('content-accord.resolver');

    expect($resolver)->toBeInstanceOf(ChainedResolver::class);

    $property = new ReflectionProperty($resolver, 'resolvers');
    $property->setAccessible(true);
    $resolvers = $property->getValue($resolver);

    expect($resolvers)->toHaveCount(2)
        ->and($resolvers[0])->toBeInstanceOf(UriVersionResolver::class)
        ->and($resolvers[1])->toBeInstanceOf(HeaderVersionResolver::class);
});

test('service provider throws when no resolver is configured', function () {
    config([
        'content-accord.versioning.resolver' => null,
    ]);

    app()->forgetInstance('content-accord.resolver');

    expect(fn () => app('content-accord.resolver'))->toThrow(InvalidArgumentException::class);
});

test('service provider builds versioning dimension with null default version', function () {
    config([
        'content-accord.versioning.default_version' => null,
        'content-accord.versioning.missing_strategy' => 'reject',
        'content-accord.versioning.versions' => [
            '1' => ['deprecated' => false, 'sunset' => null, 'deprecation_link' => null],
            '2' => ['deprecated' => false, 'sunset' => null, 'deprecation_link' => null],
        ],
    ]);

    app()->forgetInstance(VersioningDimension::class);

    $dimension = app(VersioningDimension::class);

    $defaultProperty = new ReflectionProperty($dimension, 'defaultVersion');
    $defaultProperty->setAccessible(true);
    $supportedProperty = new ReflectionProperty($dimension, 'supportedVersions');
    $supportedProperty->setAccessible(true);

    expect($defaultProperty->getValue($dimension))->toBeNull()
        ->and($supportedProperty->getValue($dimension))->toBe([1, 2]);
});

test('service provider builds versioning dimension with parsed default version', function () {
    config([
        'content-accord.versioning.default_version' => '3',
        'content-accord.versioning.missing_strategy' => 'default',
        'content-accord.versioning.versions' => [
            '3' => ['deprecated' => false, 'sunset' => null, 'deprecation_link' => null],
        ],
    ]);

    app()->forgetInstance(VersioningDimension::class);

    $dimension = app(VersioningDimension::class);

    $defaultProperty = new ReflectionProperty($dimension, 'defaultVersion');
    $defaultProperty->setAccessible(true);

    $defaultVersion = $defaultProperty->getValue($dimension);

    expect($defaultVersion)->not->toBeNull()
        ->and($defaultVersion->major)->toBe(3);
});

test('service provider uses custom resolver when configured', function () {
    config([
        'content-accord.versioning.resolver' => CustomTestResolver::class,
    ]);

    app()->forgetInstance('content-accord.resolver');

    $resolver = app('content-accord.resolver');

    expect($resolver)->toBeInstanceOf(CustomTestResolver::class);
});

test('service provider resolves dimensions from configuration', function () {
    config([
        'content-accord.dimensions' => [CustomTestDimension::class],
    ]);

    app()->forgetInstance(NegotiateContext::class);

    $middleware = app(NegotiateContext::class);

    $property = new ReflectionProperty($middleware, 'dimensions');
    $property->setAccessible(true);
    $dimensions = $property->getValue($middleware);

    expect($dimensions)->toHaveCount(1)
        ->and($dimensions[0])->toBeInstanceOf(CustomTestDimension::class);
});

class CustomTestResolver implements ContextResolver
{
    public function resolve(Request $request): mixed
    {
        return null;
    }
}

class CustomTestDimension implements NegotiationDimension
{
    public function key(): string
    {
        return 'custom';
    }

    public function resolver(): ContextResolver
    {
        return new CustomTestResolver;
    }

    public function validate(mixed $resolved, Request $request): bool
    {
        return true;
    }

    public function fallback(Request $request): mixed
    {
        return null;
    }
}
