<?php

use GaiaTools\ContentAccord\Http\Middleware\DeprecationHeaders;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;

test('adds deprecation header when route is deprecated', function () {
    $middleware = new DeprecationHeaders();

    $request = Request::create('/test');
    $route = new Route('GET', '/test', []);
    $route->setAction(['deprecated' => true]);
    $request->setRouteResolver(fn () => $route);

    $response = $middleware->handle(
        $request,
        fn ($req) => response('OK')
    );

    expect($response->headers->has('Deprecation'))->toBeTrue()
        ->and($response->headers->get('Deprecation'))->toBe('true');
});

test('does not add deprecation header when route is not deprecated', function () {
    $middleware = new DeprecationHeaders();

    $request = Request::create('/test');
    $route = new Route('GET', '/test', []);
    $request->setRouteResolver(fn () => $route);

    $response = $middleware->handle(
        $request,
        fn ($req) => response('OK')
    );

    expect($response->headers->has('Deprecation'))->toBeFalse();
});

test('adds sunset header when sunset date is provided', function () {
    $middleware = new DeprecationHeaders();

    $request = Request::create('/test');
    $route = new Route('GET', '/test', []);
    $route->setAction([
        'deprecated' => true,
        'sunset' => '2026-03-01',
    ]);
    $request->setRouteResolver(fn () => $route);

    $response = $middleware->handle(
        $request,
        fn ($req) => response('OK')
    );

    expect($response->headers->has('Sunset'))->toBeTrue();

    $sunsetHeader = $response->headers->get('Sunset');
    expect($sunsetHeader)->toContain('2026');
});

test('adds link header when deprecation link is provided', function () {
    $middleware = new DeprecationHeaders();

    $request = Request::create('/test');
    $route = new Route('GET', '/test', []);
    $route->setAction([
        'deprecated' => true,
        'deprecation_link' => 'https://docs.example.com/migration',
    ]);
    $request->setRouteResolver(fn () => $route);

    $response = $middleware->handle(
        $request,
        fn ($req) => response('OK')
    );

    expect($response->headers->has('Link'))->toBeTrue()
        ->and($response->headers->get('Link'))->toContain('https://docs.example.com/migration')
        ->and($response->headers->get('Link'))->toContain('rel="deprecation"');
});

test('adds all deprecation headers when all metadata is provided', function () {
    $middleware = new DeprecationHeaders();

    $request = Request::create('/test');
    $route = new Route('GET', '/test', []);
    $route->setAction([
        'deprecated' => true,
        'sunset' => '2026-06-01',
        'deprecation_link' => 'https://api.example.com/docs/v2',
    ]);
    $request->setRouteResolver(fn () => $route);

    $response = $middleware->handle(
        $request,
        fn ($req) => response('OK')
    );

    expect($response->headers->has('Deprecation'))->toBeTrue()
        ->and($response->headers->has('Sunset'))->toBeTrue()
        ->and($response->headers->has('Link'))->toBeTrue();
});

test('does not modify response when route has no deprecation metadata', function () {
    $middleware = new DeprecationHeaders();

    $request = Request::create('/test');
    $route = new Route('GET', '/test', []);
    $request->setRouteResolver(fn () => $route);

    $response = $middleware->handle(
        $request,
        fn ($req) => response('OK', 200, ['X-Custom' => 'value'])
    );

    expect($response->headers->has('Deprecation'))->toBeFalse()
        ->and($response->headers->has('Sunset'))->toBeFalse()
        ->and($response->headers->has('Link'))->toBeFalse()
        ->and($response->headers->get('X-Custom'))->toBe('value');
});

test('handles request without route', function () {
    $middleware = new DeprecationHeaders();

    $request = Request::create('/test');

    $response = $middleware->handle(
        $request,
        fn ($req) => response('OK')
    );

    expect($response->headers->has('Deprecation'))->toBeFalse();
});

test('deprecated false does not add headers', function () {
    $middleware = new DeprecationHeaders();

    $request = Request::create('/test');
    $route = new Route('GET', '/test', []);
    $route->setAction([
        'deprecated' => false,
        'sunset' => '2026-03-01',
    ]);
    $request->setRouteResolver(fn () => $route);

    $response = $middleware->handle(
        $request,
        fn ($req) => response('OK')
    );

    expect($response->headers->has('Deprecation'))->toBeFalse()
        ->and($response->headers->has('Sunset'))->toBeFalse();
});

test('passes request to next handler', function () {
    $middleware = new DeprecationHeaders();
    $passedRequest = null;

    $request = Request::create('/test');
    $route = new Route('GET', '/test', []);
    $request->setRouteResolver(fn () => $route);

    $middleware->handle(
        $request,
        function ($req) use (&$passedRequest) {
            $passedRequest = $req;

            return response('OK');
        }
    );

    expect($passedRequest)->toBe($request);
});
