<?php

use GaiaTools\ContentAccord\ContentAccordServiceProvider;
use GaiaTools\ContentAccord\Dimensions\VersioningDimension;
use GaiaTools\ContentAccord\Resolvers\ChainedResolver;
use GaiaTools\ContentAccord\Resolvers\Version\AcceptHeaderVersionResolver;
use GaiaTools\ContentAccord\Resolvers\Version\HeaderVersionResolver;
use GaiaTools\ContentAccord\Resolvers\Version\UriVersionResolver;
use GaiaTools\ContentAccord\Routing\PendingVersionedRouteGroup;
use Illuminate\Routing\Router;

test('service provider registers router macro and publishes config', function () {
    $provider = app()->getProvider(ContentAccordServiceProvider::class);

    $provider->boot();

    $router = app(Router::class);
    $pending = $router->apiVersion('1');

    expect($pending)->toBeInstanceOf(PendingVersionedRouteGroup::class);
});

test('service provider builds chained resolver from configuration', function () {
    config([
        'content-accord.versioning.chain' => ['uri', 'header'],
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

test('service provider falls back to uri resolver for unknown strategy', function () {
    config([
        'content-accord.versioning.chain' => null,
        'content-accord.versioning.strategy' => 'unknown',
        'content-accord.versioning.strategies.uri.parameter' => 'version',
    ]);

    app()->forgetInstance('content-accord.resolver');

    $resolver = app('content-accord.resolver');

    expect($resolver)->toBeInstanceOf(UriVersionResolver::class);
});

test('service provider builds accept resolver for accept strategy', function () {
    config([
        'content-accord.versioning.chain' => null,
        'content-accord.versioning.strategy' => 'accept',
        'content-accord.versioning.strategies.accept.vendor' => 'acme',
    ]);

    app()->forgetInstance('content-accord.resolver');

    $resolver = app('content-accord.resolver');

    expect($resolver)->toBeInstanceOf(AcceptHeaderVersionResolver::class);
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
