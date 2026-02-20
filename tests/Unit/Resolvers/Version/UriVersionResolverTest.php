<?php

use GaiaTools\ContentAccord\Resolvers\Version\UriVersionResolver;
use GaiaTools\ContentAccord\ValueObjects\ApiVersion;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;

test('extracts version from route parameter', function () {
    $request = Request::create('/api/v1/users');
    $route = new Route('GET', '/api/v{version}/users', []);
    $route->bind($request);
    $route->setParameter('version', '1');
    $request->setRouteResolver(fn () => $route);

    $resolver = new UriVersionResolver('version');
    $version = $resolver->resolve($request);

    expect($version)->toBeInstanceOf(ApiVersion::class)
        ->and($version->major)->toBe(1)
        ->and($version->minor)->toBe(0);
});

test('extracts version with minor from route parameter', function () {
    $request = Request::create('/api/v2.5/users');
    $route = new Route('GET', '/api/v{version}/users', []);
    $route->bind($request);
    $route->setParameter('version', '2.5');
    $request->setRouteResolver(fn () => $route);

    $resolver = new UriVersionResolver('version');
    $version = $resolver->resolve($request);

    expect($version)->toBeInstanceOf(ApiVersion::class)
        ->and($version->major)->toBe(2)
        ->and($version->minor)->toBe(5);
});

test('returns null when route parameter is missing', function () {
    $request = Request::create('/api/users');
    $route = new Route('GET', '/api/users', []);
    $route->bind($request);
    $request->setRouteResolver(fn () => $route);

    $resolver = new UriVersionResolver('version');

    expect($resolver->resolve($request))->toBeNull();
});

test('returns null when route is not set', function () {
    $request = Request::create('/api/users');

    $resolver = new UriVersionResolver('version');

    expect($resolver->resolve($request))->toBeNull();
});

test('returns null for invalid version format', function () {
    $request = Request::create('/api/invalid/users');
    $route = new Route('GET', '/api/{version}/users', []);
    $route->bind($request);
    $route->setParameter('version', 'invalid');
    $request->setRouteResolver(fn () => $route);

    $resolver = new UriVersionResolver('version');

    expect($resolver->resolve($request))->toBeNull();
});

test('uses custom parameter name', function () {
    $request = Request::create('/api/v3/users');
    $route = new Route('GET', '/api/v{api_version}/users', []);
    $route->bind($request);
    $route->setParameter('api_version', '3');
    $request->setRouteResolver(fn () => $route);

    $resolver = new UriVersionResolver('api_version');
    $version = $resolver->resolve($request);

    expect($version)->toBeInstanceOf(ApiVersion::class)
        ->and($version->major)->toBe(3);
});
