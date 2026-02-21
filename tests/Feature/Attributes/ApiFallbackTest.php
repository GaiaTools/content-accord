<?php

use GaiaTools\ContentAccord\Attributes\ApiFallback;
use GaiaTools\ContentAccord\Attributes\ApiVersion as ApiVersionAttribute;
use GaiaTools\ContentAccord\Routing\VersionedRouteCollection;
use Illuminate\Routing\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

// v2 route with fallback disabled via class attribute
#[ApiVersionAttribute('2')]
#[ApiFallback(false)]
class ApiFallbackDisabledController
{
    public function index(): string
    {
        return 'v2';
    }
}

// v2 route with fallback enabled via class attribute (default enabled=true)
#[ApiVersionAttribute('2')]
#[ApiFallback]
class ApiFallbackEnabledController
{
    public function index(): string
    {
        return 'v2';
    }
}

// Class says no fallback, method says yes fallback (method wins)
#[ApiVersionAttribute('2')]
#[ApiFallback(false)]
class ApiFallbackMethodOverridesClassController
{
    #[ApiFallback]
    public function index(): string
    {
        return 'v2';
    }
}

function makeFallbackConfig(): array
{
    return [
        'missing_strategy' => 'reject',
        'default_version' => '1',
        'fallback' => false,
        'versions' => ['1' => [], '2' => [], '3' => []],
        'resolver' => [\GaiaTools\ContentAccord\Resolvers\Version\HeaderVersionResolver::class],
        'strategies' => [
            'header' => ['name' => 'Api-Version'],
            'uri' => ['prefix' => 'v', 'parameter' => 'version'],
            'accept' => ['vendor' => 'myapp'],
        ],
    ];
}

function makeControllerRoute(string $controllerClass, string $method = 'index'): Route
{
    $route = new Route('GET', '/users', fn () => 'ok');
    $action = $route->getAction();
    $action['controller'] = $controllerClass . '@' . $method;
    $route->setAction($action);
    $route->setContainer(app());

    return $route;
}

function makeSimpleVersionedRoute(string $version, bool $fallback = false): Route
{
    $route = new Route('GET', '/users', fn () => 'ok');
    $route->setAction([
        'api_version' => $version,
        'fallback_enabled' => $fallback,
    ]);
    $route->setContainer(app());

    return $route;
}

test('#[ApiFallback(false)] disables fallback when requesting higher version', function () {
    $collection = new VersionedRouteCollection(makeFallbackConfig(), app());
    $collection->add(makeSimpleVersionedRoute('1'));
    $collection->add(makeControllerRoute(ApiFallbackDisabledController::class));

    $request = \Illuminate\Http\Request::create('/users', 'GET');
    $request->headers->set('Api-Version', '3');

    expect(fn () => $collection->match($request))->toThrow(NotFoundHttpException::class);
});

test('#[ApiFallback] (enabled=true) allows fallback when requesting higher version', function () {
    $collection = new VersionedRouteCollection(makeFallbackConfig(), app());
    $collection->add(makeSimpleVersionedRoute('1'));
    $collection->add(makeControllerRoute(ApiFallbackEnabledController::class));

    $request = \Illuminate\Http\Request::create('/users', 'GET');
    $request->headers->set('Api-Version', '3');

    $matched = $collection->match($request);

    expect($matched->getAction('api_version'))->toBe('2');
});

test('#[ApiFallback] on method overrides #[ApiFallback(false)] on class', function () {
    $collection = new VersionedRouteCollection(makeFallbackConfig(), app());
    $collection->add(makeSimpleVersionedRoute('1'));
    $collection->add(makeControllerRoute(ApiFallbackMethodOverridesClassController::class));

    $request = \Illuminate\Http\Request::create('/users', 'GET');
    $request->headers->set('Api-Version', '3');

    $matched = $collection->match($request);

    // Method attribute (enabled=true) wins over class attribute (enabled=false)
    expect($matched->getAction('api_version'))->toBe('2');
});

test('#[ApiFallback(false)] wins over global config fallback=true', function () {
    $config = makeFallbackConfig();
    $config['fallback'] = true; // global fallback enabled

    $collection = new VersionedRouteCollection($config, app());
    $collection->add(makeSimpleVersionedRoute('1'));
    $collection->add(makeControllerRoute(ApiFallbackDisabledController::class));

    $request = \Illuminate\Http\Request::create('/users', 'GET');
    $request->headers->set('Api-Version', '3');

    // Attribute (false) wins over global config (true)
    expect(fn () => $collection->match($request))->toThrow(NotFoundHttpException::class);
});
