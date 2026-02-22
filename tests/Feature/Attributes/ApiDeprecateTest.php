<?php

use GaiaTools\ContentAccord\Attributes\ApiDeprecate;
use GaiaTools\ContentAccord\Http\Middleware\DeprecationHeaders;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;

// Class-level: deprecated=true (default), no extra fields
#[ApiDeprecate]
class ApiDeprecateClassController
{
    public function index(): string
    {
        return 'ok';
    }

    // Method overrides class: deprecated=false wins
    #[ApiDeprecate(deprecated: false)]
    public function show(): string
    {
        return 'ok';
    }
}

// Class: deprecated=false; method: deprecated=true with sunset and link
#[ApiDeprecate(deprecated: false)]
class ApiDeprecateMethodWinsController
{
    #[ApiDeprecate(sunset: '2026-06-01', link: 'https://docs.example.com/deprecation')]
    public function index(): string
    {
        return 'ok';
    }
}

// Method-level attribute only, no class attribute
class ApiDeprecateMethodOnlyController
{
    #[ApiDeprecate(sunset: '2026-09-01', link: 'https://api.example.com/migration')]
    public function index(): string
    {
        return 'ok';
    }
}

test('#[ApiDeprecate] on class sets Deprecation header', function () {
    $middleware = new DeprecationHeaders;

    $request = Request::create('/test');
    $route = new Route('GET', '/test', []);
    $route->setAction(['controller' => ApiDeprecateClassController::class.'@index']);
    $request->setRouteResolver(fn () => $route);

    $response = $middleware->handle($request, fn ($req) => response('OK'));

    expect($response->headers->get('Deprecation'))->toBe('true')
        ->and($response->headers->has('Sunset'))->toBeFalse()
        ->and($response->headers->has('Link'))->toBeFalse();
});

test('#[ApiDeprecate(deprecated: false)] on method suppresses headers from class', function () {
    $middleware = new DeprecationHeaders;

    $request = Request::create('/test');
    $route = new Route('GET', '/test', []);
    $route->setAction(['controller' => ApiDeprecateClassController::class.'@show']);
    $request->setRouteResolver(fn () => $route);

    $response = $middleware->handle($request, fn ($req) => response('OK'));

    expect($response->headers->has('Deprecation'))->toBeFalse()
        ->and($response->headers->has('Sunset'))->toBeFalse();
});

test('#[ApiDeprecate] on method wins over class, sets sunset and link', function () {
    $middleware = new DeprecationHeaders;

    $request = Request::create('/test');
    $route = new Route('GET', '/test', []);
    $route->setAction(['controller' => ApiDeprecateMethodWinsController::class.'@index']);
    $request->setRouteResolver(fn () => $route);

    $response = $middleware->handle($request, fn ($req) => response('OK'));

    expect($response->headers->get('Deprecation'))->toBe('true')
        ->and($response->headers->get('Sunset'))->toContain('2026')
        ->and($response->headers->get('Link'))->toContain('https://docs.example.com/deprecation')
        ->and($response->headers->get('Link'))->toContain('rel="deprecation"');
});

test('#[ApiDeprecate] with sunset and link on method-only controller', function () {
    $middleware = new DeprecationHeaders;

    $request = Request::create('/test');
    $route = new Route('GET', '/test', []);
    $route->setAction(['controller' => ApiDeprecateMethodOnlyController::class.'@index']);
    $request->setRouteResolver(fn () => $route);

    $response = $middleware->handle($request, fn ($req) => response('OK'));

    expect($response->headers->get('Deprecation'))->toBe('true')
        ->and($response->headers->get('Sunset'))->toContain('2026')
        ->and($response->headers->get('Link'))->toContain('https://api.example.com/migration');
});

test('#[ApiDeprecate] wins over action array deprecated=false', function () {
    $middleware = new DeprecationHeaders;

    $request = Request::create('/test');
    $route = new Route('GET', '/test', []);
    // Action says not deprecated, but attribute says deprecated=true
    $route->setAction([
        'controller' => ApiDeprecateClassController::class.'@index',
        'deprecated' => false,
    ]);
    $request->setRouteResolver(fn () => $route);

    $response = $middleware->handle($request, fn ($req) => response('OK'));

    // Attribute (deprecated=true) wins over action (deprecated=false)
    expect($response->headers->get('Deprecation'))->toBe('true');
});

test('#[ApiDeprecate(deprecated: false)] wins over action array deprecated=true', function () {
    $middleware = new DeprecationHeaders;

    $request = Request::create('/test');
    $route = new Route('GET', '/test', []);
    // Action says deprecated, but method attribute says not deprecated
    $route->setAction([
        'controller' => ApiDeprecateClassController::class.'@show',
        'deprecated' => true,
    ]);
    $request->setRouteResolver(fn () => $route);

    $response = $middleware->handle($request, fn ($req) => response('OK'));

    // Attribute (deprecated=false) wins over action (deprecated=true)
    expect($response->headers->has('Deprecation'))->toBeFalse();
});
