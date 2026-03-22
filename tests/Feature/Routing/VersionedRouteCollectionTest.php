<?php

use GaiaTools\ContentAccord\Attributes\ApiFallback as ApiFallbackAttr;
use GaiaTools\ContentAccord\Attributes\ApiVersion as ApiVersionAttr;
use GaiaTools\ContentAccord\Exceptions\MissingVersionException;
use GaiaTools\ContentAccord\Exceptions\UnsupportedVersionException;
use GaiaTools\ContentAccord\Resolvers\Version\HeaderVersionResolver;
use GaiaTools\ContentAccord\Routing\VersionedRouteCollection;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[ApiVersionAttr('2')]
#[ApiFallbackAttr(false)]
class VersionedCollectionFallbackDisabledController
{
    public function index(): string
    {
        return 'v2';
    }
}

#[ApiVersionAttr('2')]
#[ApiFallbackAttr]
class VersionedCollectionFallbackEnabledController
{
    public function index(): string
    {
        return 'v2';
    }
}

function makeVersionedRoute(string $version, bool $fallback = false): Route
{
    $route = new Route('GET', '/users', fn () => 'ok');
    $route->setAction([
        'api_version' => $version,
        'fallback_enabled' => $fallback,
    ]);
    $route->setContainer(app());

    return $route;
}

test('matches route by header version', function () {
    $config = [
        'missing_strategy' => 'reject',
        'default_version' => '1',
        'fallback' => false,
        'versions' => ['1' => [], '2' => []],
        'resolver' => [HeaderVersionResolver::class],
        'strategies' => [
            'header' => ['name' => 'Api-Version'],
            'uri' => ['prefix' => 'v', 'parameter' => 'version'],
            'accept' => ['vendor' => 'myapp'],
        ],
    ];

    $collection = new VersionedRouteCollection($config, app());
    $collection->add(makeVersionedRoute('1'));
    $collection->add(makeVersionedRoute('2'));

    $request = Request::create('/users', 'GET');
    $request->headers->set('Api-Version', '2');

    $matched = $collection->match($request);

    expect($matched->getAction('api_version'))->toBe('2');
});

test('matches route by version metadata middleware', function () {
    $config = [
        'missing_strategy' => 'reject',
        'default_version' => '1',
        'fallback' => false,
        'versions' => ['1' => [], '2' => []],
        'resolver' => [HeaderVersionResolver::class],
        'strategies' => [
            'header' => ['name' => 'Api-Version'],
            'uri' => ['prefix' => 'v', 'parameter' => 'version'],
            'accept' => ['vendor' => 'myapp'],
        ],
    ];

    $collection = new VersionedRouteCollection($config, app());

    $routeV1 = new Route('GET', '/users', fn () => 'v1');
    $routeV1->middleware('content-accord.version:version=1');
    $routeV1->setContainer(app());

    $routeV2 = new Route('GET', '/users', fn () => 'v2');
    $routeV2->middleware('content-accord.version:version=2');
    $routeV2->setContainer(app());

    $collection->add($routeV1);
    $collection->add($routeV2);

    $request = Request::create('/users', 'GET');
    $request->headers->set('Api-Version', '2');

    $matched = $collection->match($request);

    expect($matched->getAction('api_version'))->toBe('2');
});

test('falls back to closest lower version when enabled', function () {
    $config = [
        'missing_strategy' => 'reject',
        'default_version' => '1',
        'fallback' => false,
        'versions' => ['1' => [], '2' => [], '3' => []],
        'resolver' => [HeaderVersionResolver::class],
        'strategies' => [
            'header' => ['name' => 'Api-Version'],
            'uri' => ['prefix' => 'v', 'parameter' => 'version'],
            'accept' => ['vendor' => 'myapp'],
        ],
    ];

    $collection = new VersionedRouteCollection($config, app());
    $collection->add(makeVersionedRoute('1'));
    $collection->add(makeVersionedRoute('2', true));

    $request = Request::create('/users', 'GET');
    $request->headers->set('Api-Version', '3');

    $matched = $collection->match($request);

    expect($matched->getAction('api_version'))->toBe('2');
});

test('returns not found when fallback disabled and no version match', function () {
    $config = [
        'missing_strategy' => 'reject',
        'default_version' => '1',
        'fallback' => false,
        'versions' => ['1' => [], '2' => [], '3' => []],
        'resolver' => [HeaderVersionResolver::class],
        'strategies' => [
            'header' => ['name' => 'Api-Version'],
            'uri' => ['prefix' => 'v', 'parameter' => 'version'],
            'accept' => ['vendor' => 'myapp'],
        ],
    ];

    $collection = new VersionedRouteCollection($config, app());
    $collection->add(makeVersionedRoute('1'));
    $collection->add(makeVersionedRoute('2', false));

    $request = Request::create('/users', 'GET');
    $request->headers->set('Api-Version', '3');

    $match = fn () => $collection->match($request);

    expect($match)->toThrow(NotFoundHttpException::class);
});

test('uses default version when missing strategy is default', function () {
    $config = [
        'missing_strategy' => 'default',
        'default_version' => '2',
        'fallback' => false,
        'versions' => ['1' => [], '2' => []],
        'resolver' => [HeaderVersionResolver::class],
        'strategies' => [
            'header' => ['name' => 'Api-Version'],
            'uri' => ['prefix' => 'v', 'parameter' => 'version'],
            'accept' => ['vendor' => 'myapp'],
        ],
    ];

    $collection = new VersionedRouteCollection($config, app());
    $collection->add(makeVersionedRoute('1'));
    $collection->add(makeVersionedRoute('2'));

    $request = Request::create('/users', 'GET');

    $matched = $collection->match($request);

    expect($matched->getAction('api_version'))->toBe('2');
});

test('throws when version is missing and strategy is reject', function () {
    $config = [
        'missing_strategy' => 'reject',
        'default_version' => '1',
        'fallback' => false,
        'versions' => ['1' => []],
        'resolver' => [HeaderVersionResolver::class],
        'strategies' => [
            'header' => ['name' => 'Api-Version'],
            'uri' => ['prefix' => 'v', 'parameter' => 'version'],
            'accept' => ['vendor' => 'myapp'],
        ],
    ];

    $collection = new VersionedRouteCollection($config, app());
    $collection->add(makeVersionedRoute('1'));

    $request = Request::create('/users', 'GET');

    $match = fn () => $collection->match($request);

    expect($match)->toThrow(MissingVersionException::class);
});

test('throws when requested version is unsupported', function () {
    $config = [
        'missing_strategy' => 'reject',
        'default_version' => '1',
        'fallback' => false,
        'versions' => ['1' => [], '2' => []],
        'resolver' => [HeaderVersionResolver::class],
        'strategies' => [
            'header' => ['name' => 'Api-Version'],
            'uri' => ['prefix' => 'v', 'parameter' => 'version'],
            'accept' => ['vendor' => 'myapp'],
        ],
    ];

    $collection = new VersionedRouteCollection($config, app());
    $collection->add(makeVersionedRoute('1'));
    $collection->add(makeVersionedRoute('2'));

    $request = Request::create('/users', 'GET');
    $request->headers->set('Api-Version', '9');

    $match = fn () => $collection->match($request);

    expect($match)->toThrow(UnsupportedVersionException::class);
});

test('#[ApiFallback(false)] attribute disables fallback in collection', function () {
    $config = [
        'missing_strategy' => 'reject',
        'default_version' => '1',
        'fallback' => false,
        'versions' => ['1' => [], '2' => [], '3' => []],
        'resolver' => [HeaderVersionResolver::class],
        'strategies' => [
            'header' => ['name' => 'Api-Version'],
            'uri' => ['prefix' => 'v', 'parameter' => 'version'],
            'accept' => ['vendor' => 'myapp'],
        ],
    ];

    $collection = new VersionedRouteCollection($config, app());
    $collection->add(makeVersionedRoute('1'));

    $v2Route = new Route('GET', '/users', fn () => 'v2');
    $action = $v2Route->getAction();
    $action['controller'] = VersionedCollectionFallbackDisabledController::class.'@index';
    $v2Route->setAction($action);
    $v2Route->setContainer(app());
    $collection->add($v2Route);

    $request = Request::create('/users', 'GET');
    $request->headers->set('Api-Version', '3');

    expect(fn () => $collection->match($request))->toThrow(NotFoundHttpException::class);
});

test('#[ApiFallback] attribute enables fallback in collection', function () {
    $config = [
        'missing_strategy' => 'reject',
        'default_version' => '1',
        'fallback' => false,
        'versions' => ['1' => [], '2' => [], '3' => []],
        'resolver' => [HeaderVersionResolver::class],
        'strategies' => [
            'header' => ['name' => 'Api-Version'],
            'uri' => ['prefix' => 'v', 'parameter' => 'version'],
            'accept' => ['vendor' => 'myapp'],
        ],
    ];

    $collection = new VersionedRouteCollection($config, app());
    $collection->add(makeVersionedRoute('1'));

    $v2Route = new Route('GET', '/users', fn () => 'v2');
    $action = $v2Route->getAction();
    $action['controller'] = VersionedCollectionFallbackEnabledController::class.'@index';
    $v2Route->setAction($action);
    $v2Route->setContainer(app());
    $collection->add($v2Route);

    $request = Request::create('/users', 'GET');
    $request->headers->set('Api-Version', '3');

    $matched = $collection->match($request);

    expect($matched->getAction('api_version'))->toBe('2');
});
