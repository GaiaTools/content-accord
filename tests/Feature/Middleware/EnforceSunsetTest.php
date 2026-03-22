<?php

use GaiaTools\ContentAccord\Http\Middleware\EnforceSunset;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;

test('returns 410 when sunset date has passed', function () {
    $middleware = new EnforceSunset;

    $request = Request::create('/test');
    $route = new Route('GET', '/test', []);
    $route->setAction([
        'deprecated' => true,
        'sunset' => '2020-01-01',
    ]);
    $request->setRouteResolver(fn () => $route);

    $response = $middleware->handle($request, fn ($req) => response('OK'));

    expect($response->getStatusCode())->toBe(410)
        ->and(json_decode($response->getContent(), true)['message'])->toContain('sunset')
        ->and(json_decode($response->getContent(), true)['sunset'])->toBe('2020-01-01');
});

test('passes through when sunset date is in the future', function () {
    $middleware = new EnforceSunset;

    $request = Request::create('/test');
    $route = new Route('GET', '/test', []);
    $route->setAction([
        'deprecated' => true,
        'sunset' => '2099-01-01',
    ]);
    $request->setRouteResolver(fn () => $route);

    $response = $middleware->handle($request, fn ($req) => response('OK'));

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getContent())->toBe('OK');
});

test('passes through when no sunset is configured', function () {
    $middleware = new EnforceSunset;

    $request = Request::create('/test');
    $route = new Route('GET', '/test', []);
    $request->setRouteResolver(fn () => $route);

    $response = $middleware->handle($request, fn ($req) => response('OK'));

    expect($response->getStatusCode())->toBe(200);
});

test('passes through when route is null', function () {
    $middleware = new EnforceSunset;

    $request = Request::create('/test');

    $response = $middleware->handle($request, fn ($req) => response('OK'));

    expect($response->getStatusCode())->toBe(200);
});

test('passes through when sunset date is unparseable', function () {
    $middleware = new EnforceSunset;

    $request = Request::create('/test');
    $route = new Route('GET', '/test', []);
    $route->setAction(['sunset' => 'not-a-date']);
    $request->setRouteResolver(fn () => $route);

    $response = $middleware->handle($request, fn ($req) => response('OK'));

    expect($response->getStatusCode())->toBe(200);
});

test('passes request to next handler when not sunset', function () {
    $middleware = new EnforceSunset;
    $passedRequest = null;

    $request = Request::create('/test');
    $route = new Route('GET', '/test', []);
    $route->setAction(['sunset' => '2099-01-01']);
    $request->setRouteResolver(fn () => $route);

    $middleware->handle($request, function ($req) use (&$passedRequest) {
        $passedRequest = $req;

        return response('OK');
    });

    expect($passedRequest)->toBe($request);
});

test('410 response includes json content type', function () {
    $middleware = new EnforceSunset;

    $request = Request::create('/test');
    $route = new Route('GET', '/test', []);
    $route->setAction(['sunset' => '2020-06-15']);
    $request->setRouteResolver(fn () => $route);

    $response = $middleware->handle($request, fn ($req) => response('OK'));

    expect($response->headers->get('Content-Type'))->toContain('application/json');
});
