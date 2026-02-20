<?php

use GaiaTools\ContentAccord\Exceptions\MissingVersionException;
use GaiaTools\ContentAccord\Exceptions\UnsupportedVersionException;
use GaiaTools\ContentAccord\Routing\VersionedRouteCollection;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
        'strategy' => 'header',
        'missing_strategy' => 'reject',
        'default_version' => '1',
        'fallback' => false,
        'versions' => ['1' => [], '2' => []],
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

test('falls back to closest lower version when enabled', function () {
    $config = [
        'strategy' => 'header',
        'missing_strategy' => 'reject',
        'default_version' => '1',
        'fallback' => false,
        'versions' => ['1' => [], '2' => [], '3' => []],
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
        'strategy' => 'header',
        'missing_strategy' => 'reject',
        'default_version' => '1',
        'fallback' => false,
        'versions' => ['1' => [], '2' => [], '3' => []],
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
        'strategy' => 'header',
        'missing_strategy' => 'default',
        'default_version' => '2',
        'fallback' => false,
        'versions' => ['1' => [], '2' => []],
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
        'strategy' => 'header',
        'missing_strategy' => 'reject',
        'default_version' => '1',
        'fallback' => false,
        'versions' => ['1' => []],
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
        'strategy' => 'header',
        'missing_strategy' => 'reject',
        'default_version' => '1',
        'fallback' => false,
        'versions' => ['1' => [], '2' => []],
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
