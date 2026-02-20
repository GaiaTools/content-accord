<?php

use GaiaTools\ContentAccord\Routing\PendingVersionedRouteGroup;
use Illuminate\Routing\Router;
use Illuminate\Routing\RouteCollection;
use Mockery as Mockery;

afterEach(function () {
    Mockery::close();
});

test('builds attributes for uri strategy with metadata and fallback', function () {
    $config = [
        'strategy' => 'uri',
        'strategies' => [
            'uri' => ['prefix' => 'v', 'parameter' => 'version'],
        ],
    ];

    $router = app(Router::class);
    $group = new PendingVersionedRouteGroup($router, '2', $config);

    $group->prefix('api')
        ->middleware(['auth'])
        ->middleware('throttle')
        ->deprecated()
        ->sunsetDate('2030-01-01')
        ->deprecationLink('https://example.test/deprecation')
        ->fallbackToVersion();

    $method = new ReflectionMethod($group, 'buildGroupAttributes');
    $method->setAccessible(true);

    $attributes = $method->invoke($group);

    expect($attributes)->toMatchArray([
        'prefix' => 'api/v2',
        'deprecated' => true,
        'sunset' => '2030-01-01',
        'deprecation_link' => 'https://example.test/deprecation',
        'api_version' => '2',
        'fallback_enabled' => true,
    ]);

    expect($attributes['middleware'])
        ->toContain('auth')
        ->toContain('throttle')
        ->toContain(\GaiaTools\ContentAccord\Http\Middleware\NegotiateContext::class)
        ->toContain(\GaiaTools\ContentAccord\Http\Middleware\DeprecationHeaders::class);
});

test('builds attributes for header strategy without uri prefix', function () {
    $config = [
        'strategy' => 'header',
        'strategies' => [
            'uri' => ['prefix' => 'v', 'parameter' => 'version'],
        ],
    ];

    $router = app(Router::class);
    $group = new PendingVersionedRouteGroup($router, '1', $config);

    $method = new ReflectionMethod($group, 'buildGroupAttributes');
    $method->setAccessible(true);

    $attributes = $method->invoke($group);

    expect($attributes['api_version'])->toBe('1')
        ->and($attributes['fallback_enabled'])->toBeFalse()
        ->and($attributes['middleware'])->toContain(\GaiaTools\ContentAccord\Http\Middleware\NegotiateContext::class);
});

test('builds uri prefix when no custom prefix provided', function () {
    $config = [
        'strategy' => 'uri',
        'strategies' => [
            'uri' => ['prefix' => 'v', 'parameter' => 'version'],
        ],
    ];

    $router = app(Router::class);
    $group = new PendingVersionedRouteGroup($router, '4', $config);

    $method = new ReflectionMethod($group, 'buildGroupAttributes');
    $method->setAccessible(true);

    $attributes = $method->invoke($group);

    expect($attributes['prefix'])->toBe('v4')
        ->and($attributes['api_version'])->toBe('4')
        ->and($attributes['fallback_enabled'])->toBeFalse()
        ->and($attributes['middleware'])->toContain(\GaiaTools\ContentAccord\Http\Middleware\NegotiateContext::class);
});

test('builds attributes for header strategy with custom prefix', function () {
    $config = [
        'strategy' => 'header',
        'strategies' => [
            'uri' => ['prefix' => 'v', 'parameter' => 'version'],
        ],
    ];

    $router = app(Router::class);
    $group = new PendingVersionedRouteGroup($router, '1', $config);
    $group->prefix('api');

    $method = new ReflectionMethod($group, 'buildGroupAttributes');
    $method->setAccessible(true);

    $attributes = $method->invoke($group);

    expect($attributes['prefix'])->toBe('api')
        ->and($attributes['api_version'])->toBe('1')
        ->and($attributes['fallback_enabled'])->toBeFalse()
        ->and($attributes['middleware'])->toContain(\GaiaTools\ContentAccord\Http\Middleware\NegotiateContext::class);
});

test('group passes attributes to router', function () {
    $config = [
        'strategy' => 'uri',
        'strategies' => [
            'uri' => ['prefix' => 'v', 'parameter' => 'version'],
        ],
    ];

    $router = Mockery::mock(Router::class);
    $group = new PendingVersionedRouteGroup($router, '3', $config);
    $group->prefix('api')->fallbackToVersion(false);

    $router->shouldReceive('getRoutes')
        ->twice()
        ->andReturn(new RouteCollection());

    $router->shouldReceive('group')
        ->once()
        ->with(Mockery::on(function (array $attributes) {
            return $attributes['prefix'] === 'api/v3'
                && $attributes['api_version'] === '3'
                && $attributes['fallback_enabled'] === false
                && in_array(\GaiaTools\ContentAccord\Http\Middleware\NegotiateContext::class, $attributes['middleware'], true);
        }), Mockery::type(Closure::class));

    $group->group(function () {
        // no-op
    });
});
