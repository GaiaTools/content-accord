<?php

use GaiaTools\ContentAccord\Events\DeprecatedVersionAccessed;
use GaiaTools\ContentAccord\Http\Middleware\DeprecationHeaders;
use GaiaTools\ContentAccord\Http\NegotiatedContext;
use GaiaTools\ContentAccord\ValueObjects\ApiVersion;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Event;

test('DeprecatedVersionAccessed event is fired when deprecated version is accessed', function () {
    Event::fake([DeprecatedVersionAccessed::class]);

    $context = app(NegotiatedContext::class);
    $version = new ApiVersion(1);
    $context->set('version', $version);

    $middleware = new DeprecationHeaders;

    $request = Request::create('/test');
    $route = new Route('GET', '/test', []);
    $route->setAction(['deprecated' => true]);
    $request->setRouteResolver(fn () => $route);

    $middleware->handle($request, fn ($req) => response('OK'));

    Event::assertDispatched(DeprecatedVersionAccessed::class, function (DeprecatedVersionAccessed $event) use ($version) {
        return $event->version === $version;
    });
});

test('DeprecatedVersionAccessed event is not fired for non-deprecated routes', function () {
    Event::fake([DeprecatedVersionAccessed::class]);

    $context = app(NegotiatedContext::class);
    $context->set('version', new ApiVersion(1));

    $middleware = new DeprecationHeaders;

    $request = Request::create('/test');
    $route = new Route('GET', '/test', []);
    $route->setAction(['deprecated' => false]);
    $request->setRouteResolver(fn () => $route);

    $middleware->handle($request, fn ($req) => response('OK'));

    Event::assertNotDispatched(DeprecatedVersionAccessed::class);
});

test('DeprecatedVersionAccessed event carries version and request', function () {
    Event::fake([DeprecatedVersionAccessed::class]);

    $context = app(NegotiatedContext::class);
    $version = new ApiVersion(2);
    $context->set('version', $version);

    $middleware = new DeprecationHeaders;

    $request = Request::create('/api/v2/users');
    $route = new Route('GET', '/api/v2/users', []);
    $route->setAction(['deprecated' => true]);
    $request->setRouteResolver(fn () => $route);

    $middleware->handle($request, fn ($req) => response('OK'));

    Event::assertDispatched(DeprecatedVersionAccessed::class, function (DeprecatedVersionAccessed $event) use ($version, $request) {
        return $event->version === $version && $event->request === $request;
    });
});

test('DeprecatedVersionAccessed event is not fired when no version is in context', function () {
    Event::fake([DeprecatedVersionAccessed::class]);

    // NegotiatedContext has no version set
    $middleware = new DeprecationHeaders;

    $request = Request::create('/test');
    $route = new Route('GET', '/test', []);
    $route->setAction(['deprecated' => true]);
    $request->setRouteResolver(fn () => $route);

    $middleware->handle($request, fn ($req) => response('OK'));

    Event::assertNotDispatched(DeprecatedVersionAccessed::class);
});
