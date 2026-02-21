<?php

use GaiaTools\ContentAccord\Http\Middleware\ApiVersionMetadata;
use GaiaTools\ContentAccord\Http\NegotiatedContext;
use GaiaTools\ContentAccord\Resolvers\Version\UriVersionResolver;
use GaiaTools\ContentAccord\Routing\RouteVersionBuilder;
use GaiaTools\ContentAccord\ValueObjects\ApiVersion;
use Illuminate\Support\Facades\Route;

test('Route::apiVersion macro is registered', function () {
    expect(Route::hasMacro('apiVersion'))->toBeTrue();
});

test('Route::apiVersion returns RouteVersionBuilder', function () {
    $pending = Route::apiVersion('1');

    expect($pending)->toBeInstanceOf(RouteVersionBuilder::class);
});

test('URI strategy auto-adds version prefix to route URIs', function () {
    config([
        'content-accord.versioning.resolver' => [UriVersionResolver::class],
        'content-accord.versioning.strategies.uri.prefix' => 'v',
    ]);

    Route::apiVersion('2')->prefix('api')->group(function () {
        Route::get('/users', fn () => 'ok');
    });

    $routes = collect(app('router')->getRoutes()->getRoutes());
    $usersRoute = $routes->first(fn ($r) => str_contains($r->uri(), 'users'));

    expect($usersRoute)->not->toBeNull()
        ->and($usersRoute->uri())->toBe('api/v2/users');
});

test('URI strategy with no prefix produces version-only prefix', function () {
    config([
        'content-accord.versioning.resolver' => [UriVersionResolver::class],
        'content-accord.versioning.strategies.uri.prefix' => 'v',
    ]);

    Route::apiVersion('1')->group(function () {
        Route::get('/users', fn () => 'ok');
    });

    $routes = collect(app('router')->getRoutes()->getRoutes());
    $usersRoute = $routes->first(fn ($r) => str_contains($r->uri(), 'users'));

    expect($usersRoute)->not->toBeNull()
        ->and($usersRoute->uri())->toBe('v1/users');
});

test('header resolver does not add version prefix', function () {
    config([
        'content-accord.versioning.resolver' => [\GaiaTools\ContentAccord\Resolvers\Version\HeaderVersionResolver::class],
    ]);

    Route::apiVersion('2')->prefix('api')->group(function () {
        Route::get('/users', fn () => 'ok');
    });

    $routes = collect(app('router')->getRoutes()->getRoutes());
    $usersRoute = $routes->first(fn ($r) => str_contains($r->uri(), 'users'));

    expect($usersRoute)->not->toBeNull()
        ->and($usersRoute->uri())->toBe('api/users');
});

test('accept resolver does not add version prefix', function () {
    config([
        'content-accord.versioning.resolver' => [\GaiaTools\ContentAccord\Resolvers\Version\AcceptHeaderVersionResolver::class],
    ]);

    Route::apiVersion('2')->prefix('api')->group(function () {
        Route::get('/users', fn () => 'ok');
    });

    $routes = collect(app('router')->getRoutes()->getRoutes());
    $usersRoute = $routes->first(fn ($r) => str_contains($r->uri(), 'users'));

    expect($usersRoute)->not->toBeNull()
        ->and($usersRoute->uri())->toBe('api/users');
});

test('version middleware string is applied to routes', function () {
    config([
        'content-accord.versioning.resolver' => [UriVersionResolver::class],
    ]);

    Route::apiVersion('3')->prefix('api')->group(function () {
        Route::get('/items', fn () => 'ok');
    });

    $routes = collect(app('router')->getRoutes()->getRoutes());
    $route = $routes->first(fn ($r) => str_contains($r->uri(), 'items'));

    $middleware = $route->getAction('middleware');
    $middleware = is_array($middleware) ? $middleware : [$middleware];

    $versionMiddleware = collect($middleware)->first(
        fn ($m) => is_string($m) && str_starts_with($m, ApiVersionMetadata::class . ':')
    );

    expect($versionMiddleware)->not->toBeNull()
        ->and($versionMiddleware)->toContain('version=3');
});

test('deprecated sunsetDate and deprecationLink are encoded in the middleware string', function () {
    config([
        'content-accord.versioning.resolver' => [\GaiaTools\ContentAccord\Resolvers\Version\HeaderVersionResolver::class],
    ]);

    Route::apiVersion('1')
        ->prefix('api')
        ->deprecated()
        ->sunsetDate('2026-03-01')
        ->deprecationLink('https://docs.example.com/migration')
        ->group(function () {
            Route::get('/users', fn () => 'ok');
        });

    $routes = collect(app('router')->getRoutes()->getRoutes());
    $route = $routes->first(fn ($r) => str_contains($r->uri(), 'users'));

    $middleware = $route->getAction('middleware');
    $middleware = is_array($middleware) ? $middleware : [$middleware];

    $versionMiddleware = collect($middleware)->first(
        fn ($m) => is_string($m) && str_starts_with($m, ApiVersionMetadata::class . ':')
    );

    expect($versionMiddleware)->not->toBeNull()
        ->and($versionMiddleware)->toContain('deprecated=true')
        ->and($versionMiddleware)->toContain('sunset=2026-03-01')
        ->and($versionMiddleware)->toContain('link=https://docs.example.com/migration');
});

test('fallback is encoded in the middleware string', function () {
    config([
        'content-accord.versioning.resolver' => [\GaiaTools\ContentAccord\Resolvers\Version\HeaderVersionResolver::class],
    ]);

    Route::apiVersion('2')
        ->fallback(true)
        ->group(function () {
            Route::get('/posts', fn () => 'ok');
        });

    $routes = collect(app('router')->getRoutes()->getRoutes());
    $route = $routes->first(fn ($r) => str_contains($r->uri(), 'posts'));

    $middleware = $route->getAction('middleware');
    $middleware = is_array($middleware) ? $middleware : [$middleware];

    $versionMiddleware = collect($middleware)->first(
        fn ($m) => is_string($m) && str_starts_with($m, ApiVersionMetadata::class . ':')
    );

    expect($versionMiddleware)->not->toBeNull()
        ->and($versionMiddleware)->toContain('fallback=true');
});

test('extra middleware is applied alongside version middleware', function () {
    config([
        'content-accord.versioning.resolver' => [\GaiaTools\ContentAccord\Resolvers\Version\HeaderVersionResolver::class],
    ]);

    Route::apiVersion('1')
        ->middleware(['auth:sanctum'])
        ->group(function () {
            Route::get('/protected', fn () => 'ok');
        });

    $routes = collect(app('router')->getRoutes()->getRoutes());
    $route = $routes->first(fn ($r) => str_contains($r->uri(), 'protected'));

    $middleware = $route->getAction('middleware');
    $middleware = is_array($middleware) ? $middleware : [$middleware];

    $hasVersionMiddleware = collect($middleware)->contains(
        fn ($m) => is_string($m) && str_starts_with($m, ApiVersionMetadata::class . ':')
    );

    expect($hasVersionMiddleware)->toBeTrue()
        ->and($middleware)->toContain('auth:sanctum');
});

test('apiVersion helper returns null when no context is set', function () {
    $result = apiVersion();

    expect($result)->toBeNull();
});

test('apiVersion helper returns the ApiVersion when context is populated', function () {
    $version = ApiVersion::parse('2');
    app(NegotiatedContext::class)->set('version', $version);

    $result = apiVersion();

    expect($result)->toBe($version);
});
