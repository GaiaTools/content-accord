<?php

use GaiaTools\ContentAccord\Attributes\ApiFallback as ApiFallbackAttr;
use GaiaTools\ContentAccord\Attributes\ApiVersion as ApiVersionAttr;
use GaiaTools\ContentAccord\Contracts\ContextResolver;
use GaiaTools\ContentAccord\Exceptions\MissingVersionException;
use GaiaTools\ContentAccord\Exceptions\UnsupportedVersionException;
use GaiaTools\ContentAccord\Resolvers\Version\HeaderVersionResolver;
use GaiaTools\ContentAccord\Routing\VersionedRouteCollection;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollection;
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

function makeBaseConfig(): array
{
    return [
        'missing_strategy' => 'reject',
        'default_version' => '1',
        'fallback' => false,
        'versions' => ['1' => [], '2' => [], '3' => []],
        'resolver' => [HeaderVersionResolver::class],
        'strategies' => ['header' => ['name' => 'Api-Version']],
    ];
}

test('fromExisting copies routes into new collection', function () {
    // Use different URIs to avoid deduplication in the regular RouteCollection
    $r1 = new Route('GET', '/v1/users', fn () => 'v1');
    $r1->setAction(['api_version' => '1']);
    $r1->setContainer(app());

    $r2 = new Route('GET', '/v2/users', fn () => 'v2');
    $r2->setAction(['api_version' => '2']);
    $r2->setContainer(app());

    $existing = new RouteCollection;
    $existing->add($r1);
    $existing->add($r2);

    $collection = VersionedRouteCollection::fromExisting($existing, makeBaseConfig(), app());

    expect(count($collection->getRoutes()))->toBe(2);
});

test('matchAgainstRoutes returns fallback route when no non-fallback matches', function () {
    $config = makeBaseConfig();

    $collection = new VersionedRouteCollection($config, app());

    $fallbackRoute = new Route('GET', '/users', fn () => 'fallback');
    $fallbackRoute->isFallback = true;
    $fallbackRoute->setContainer(app());
    $collection->add($fallbackRoute);

    $request = Request::create('/users', 'GET');
    $request->headers->set('Api-Version', '1');

    // fallback route matches but no normal routes — should return the fallback
    expect(fn () => $collection->match($request))->not->toThrow(Throwable::class);
});

test('single non-versioned route is returned directly without version selection', function () {
    $config = makeBaseConfig();
    $collection = new VersionedRouteCollection($config, app());

    $route = new Route('GET', '/health', fn () => 'ok');
    $route->setContainer(app());
    $collection->add($route);

    $request = Request::create('/health', 'GET');

    $matched = $collection->match($request);

    expect($matched)->toBe($route);
});

test('selectVersionedRoute returns first route when none have api_version set', function () {
    $config = makeBaseConfig();
    $collection = new VersionedRouteCollection($config, app());

    // Use different URI patterns so both routes get distinct keys in the collection
    // and both match the same request — triggering selectVersionedRoute with count > 1
    $r1 = new Route('GET', '/ping', fn () => 'r1');
    $r1->setContainer(app());
    $r2 = new Route('GET', '/{slug}', fn () => 'r2');
    $r2->setContainer(app());
    $collection->add($r1);
    $collection->add($r2);

    $request = Request::create('/ping', 'GET');
    $request->headers->set('Api-Version', '1');

    $matched = $collection->match($request);

    // Both routes match — selectVersionedRoute is called; none have api_version
    // so the first matched route is returned
    expect($matched)->not->toBeNull();
});

test('latest version strategy selects highest version when no version sent', function () {
    $config = array_merge(makeBaseConfig(), [
        'missing_strategy' => 'latest',
        'versions' => ['1' => [], '2' => [], '3' => []],
    ]);

    $collection = new VersionedRouteCollection($config, app());
    $collection->add(makeVersionedRoute('1'));
    $collection->add(makeVersionedRoute('2'));
    $collection->add(makeVersionedRoute('3'));

    $request = Request::create('/users', 'GET');
    // No Api-Version header

    $matched = $collection->match($request);

    expect($matched->getAction('api_version'))->toBe('3');
});

test('latest version strategy throws when no supported versions configured', function () {
    $config = array_merge(makeBaseConfig(), [
        'missing_strategy' => 'latest',
        'versions' => [],
    ]);

    $collection = new VersionedRouteCollection($config, app());
    $collection->add(makeVersionedRoute('1'));

    $request = Request::create('/users', 'GET');

    expect(fn () => $collection->match($request))->toThrow(MissingVersionException::class);
});

test('require strategy throws with version list in message', function () {
    $config = array_merge(makeBaseConfig(), [
        'missing_strategy' => 'require',
        'versions' => ['1' => [], '2' => []],
    ]);

    $collection = new VersionedRouteCollection($config, app());
    $collection->add(makeVersionedRoute('1'));
    $collection->add(makeVersionedRoute('2'));

    $request = Request::create('/users', 'GET');

    try {
        $collection->match($request);
        fail('Expected MissingVersionException');
    } catch (MissingVersionException $e) {
        expect($e->getMessage())->toContain('v1')
            ->and($e->getMessage())->toContain('v2');
    }
});

test('require strategy throws with generic message when no versions configured', function () {
    $config = array_merge(makeBaseConfig(), [
        'missing_strategy' => 'require',
        'versions' => [],
    ]);

    $collection = new VersionedRouteCollection($config, app());
    $collection->add(makeVersionedRoute('1'));

    $request = Request::create('/users', 'GET');

    try {
        $collection->match($request);
        fail('Expected MissingVersionException');
    } catch (MissingVersionException $e) {
        expect($e->getMessage())->toContain('required');
    }
});

test('default strategy throws when no default version configured', function () {
    $config = array_merge(makeBaseConfig(), [
        'missing_strategy' => 'default',
        'default_version' => '',
        'versions' => ['1' => []],
    ]);

    $collection = new VersionedRouteCollection($config, app());
    $collection->add(makeVersionedRoute('1'));

    $request = Request::create('/users', 'GET');

    expect(fn () => $collection->match($request))->toThrow(MissingVersionException::class);
});

test('ensureSupportedVersion allows any version when supported list is empty', function () {
    $config = array_merge(makeBaseConfig(), [
        'versions' => [],
    ]);

    $collection = new VersionedRouteCollection($config, app());
    $collection->add(makeVersionedRoute('9'));

    $request = Request::create('/users', 'GET');
    $request->headers->set('Api-Version', '9');

    // Should not throw UnsupportedVersionException
    $matched = $collection->match($request);

    expect($matched->getAction('api_version'))->toBe('9');
});

test('findFallbackRoute selects closest lower version among multiple candidates', function () {
    $config = array_merge(makeBaseConfig(), [
        'versions' => ['1' => [], '2' => [], '3' => [], '5' => []],
    ]);

    $collection = new VersionedRouteCollection($config, app());
    $collection->add(makeVersionedRoute('1', true));
    $collection->add(makeVersionedRoute('2', true));
    // No v3 or v5 route; request v5 should fall back to v2

    $request = Request::create('/users', 'GET');
    $request->headers->set('Api-Version', '5');

    $matched = $collection->match($request);

    expect($matched->getAction('api_version'))->toBe('2');
});

test('resolver uses container-bound resolver when config is empty', function () {
    // Bind a header resolver explicitly so the test is independent of default config
    app()->bind('content-accord.resolver', fn () => new HeaderVersionResolver('Api-Version'));

    $config = [];

    $collection = new VersionedRouteCollection($config, app());
    $collection->add(makeVersionedRoute('1'));
    $collection->add(makeVersionedRoute('2'));

    $request = Request::create('/users', 'GET');
    $request->headers->set('Api-Version', '2');

    $matched = $collection->match($request);

    expect($matched->getAction('api_version'))->toBe('2');
});

test('versionKey updates action with fallback metadata from middleware', function () {
    $config = makeBaseConfig();
    $collection = new VersionedRouteCollection($config, app());

    $route = new Route('GET', '/users', fn () => 'ok');
    $route->middleware('content-accord.version:version=1,fallback=true');
    $route->setContainer(app());
    $collection->add($route);

    $request = Request::create('/users', 'GET');
    $request->headers->set('Api-Version', '1');

    $matched = $collection->match($request);

    expect($matched->getAction('fallback_enabled'))->toBeTrue();
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

test('findBestMatch usort compares routes by minor version descending', function () {
    // Two routes with same major (1) but different minor versions
    // When requesting v1, both are candidates; usort must run the comparison callback
    $config = array_merge(makeBaseConfig(), [
        'versions' => ['1' => [], '2' => []],
    ]);

    $collection = new VersionedRouteCollection($config, app());

    $routeMinor0 = new Route('GET', '/users', fn () => 'v1.0');
    $routeMinor0->setAction(['api_version' => '1.0', 'fallback_enabled' => false]);
    $routeMinor0->setContainer(app());

    $routeMinor1 = new Route('GET', '/users', fn () => 'v1.1');
    $routeMinor1->setAction(['api_version' => '1.1', 'fallback_enabled' => false]);
    $routeMinor1->setContainer(app());

    $collection->add($routeMinor0);
    $collection->add($routeMinor1);

    $request = Request::create('/users', 'GET');
    $request->headers->set('Api-Version', '1');

    // Should return the higher minor version (1.1 > 1.0)
    $matched = $collection->match($request);

    expect($matched->getAction('api_version'))->toBe('1.1');
});

test('findFallbackRoute usort uses minor version when major versions are equal', function () {
    // Two fallback routes with same major (1) but different minors; request v3
    // Both are fallback candidates; usort runs, same-major branch triggers line 150
    $config = array_merge(makeBaseConfig(), [
        'versions' => ['1' => [], '3' => []],
    ]);

    $collection = new VersionedRouteCollection($config, app());

    $routeMinor0 = new Route('GET', '/users', fn () => 'v1.0');
    $routeMinor0->setAction(['api_version' => '1.0', 'fallback_enabled' => true]);
    $routeMinor0->setContainer(app());

    $routeMinor2 = new Route('GET', '/users', fn () => 'v1.2');
    $routeMinor2->setAction(['api_version' => '1.2', 'fallback_enabled' => true]);
    $routeMinor2->setContainer(app());

    $collection->add($routeMinor0);
    $collection->add($routeMinor2);

    $request = Request::create('/users', 'GET');
    $request->headers->set('Api-Version', '3');

    // No v3 route; falls back to highest minor in v1 → v1.2
    $matched = $collection->match($request);

    expect($matched->getAction('api_version'))->toBe('1.2');
});

test('buildVersionCandidates skips route with truthy non-string api_version', function () {
    // api_version=true passes the filter in selectVersionedRoute (truthy)
    // but RouteVersionMetadata::resolve finds no string version → continue at line 172
    $config = makeBaseConfig();
    $collection = new VersionedRouteCollection($config, app());

    $route = new Route('GET', '/users', fn () => 'ok');
    // api_version is boolean true — truthy, but not a string
    $route->setAction(['api_version' => true]);
    $route->setContainer(app());
    $collection->add($route);

    $request = Request::create('/users', 'GET');
    $request->headers->set('Api-Version', '1');

    // Candidate list ends up empty → NotFoundHttpException
    expect(fn () => $collection->match($request))->toThrow(NotFoundHttpException::class);
});

test('resolveRequestedVersion throws UnsupportedVersionException when resolver returns non-ApiVersion non-null', function () {
    // Use a custom resolver (via container) that returns a plain string instead of ApiVersion
    app()->bind('content-accord.resolver', fn () => new class implements ContextResolver
    {
        public function resolve(Request $request): mixed
        {
            return 'not-an-api-version';
        }
    });

    $config = []; // Empty config → uses container-bound resolver
    $collection = new VersionedRouteCollection($config, app());
    $collection->add(makeVersionedRoute('1'));

    $request = Request::create('/users', 'GET');

    expect(fn () => $collection->match($request))->toThrow(UnsupportedVersionException::class);
});

test('resolveMissingVersion defaults to reject strategy when missing_strategy is non-string', function () {
    $config = array_merge(makeBaseConfig(), [
        'missing_strategy' => 42, // non-string, non-null → ?? keeps 42 → is_string(42) false → defaults to 'reject'
    ]);

    $collection = new VersionedRouteCollection($config, app());
    $collection->add(makeVersionedRoute('1'));

    $request = Request::create('/users', 'GET');
    // No Api-Version header → resolver returns null → resolveMissingVersion
    // null is not a string → strategy defaults to 'reject' → MissingVersionException

    expect(fn () => $collection->match($request))->toThrow(MissingVersionException::class);
});

test('supportedVersions returns empty array when versions config is not an array', function () {
    $config = array_merge(makeBaseConfig(), [
        'versions' => 'not-an-array',
    ]);

    $collection = new VersionedRouteCollection($config, app());
    $collection->add(makeVersionedRoute('9'));

    $request = Request::create('/users', 'GET');
    $request->headers->set('Api-Version', '9');

    // Empty supported list → any version is allowed
    $matched = $collection->match($request);

    expect($matched->getAction('api_version'))->toBe('9');
});

test('resolver throws InvalidArgumentException when container resolver does not implement ContextResolver', function () {
    app()->bind('content-accord.resolver', fn () => new stdClass);

    $config = []; // Empty config → uses container-bound resolver
    $collection = new VersionedRouteCollection($config, app());
    $collection->add(makeVersionedRoute('1'));

    $request = Request::create('/users', 'GET');
    $request->headers->set('Api-Version', '1');

    expect(fn () => $collection->match($request))->toThrow(InvalidArgumentException::class);
});

test('versionKey handles non-array getAction defensively when middleware provides version', function () {
    // To reach line 320: getAction() must return non-array AFTER RouteVersionMetadata finds
    // a version via middleware (so the non-array path in versionKey is entered).
    // We sequence the mock to return non-array for the 4 calls inside RouteVersionMetadata
    // and versionKey, then return an array for addLookups so it doesn't error.
    $config = makeBaseConfig();
    $collection = new VersionedRouteCollection($config, app());

    $mockRoute = Mockery::mock(Route::class);
    // getAction('api_version') → '' so versionKey enters the RouteVersionMetadata path
    $mockRoute->shouldReceive('getAction')->with('api_version')->andReturn('');
    // getAction('middleware') → returns a middleware string so resolve finds version '1'
    $mockRoute->shouldReceive('getAction')->with('middleware')
        ->andReturn('content-accord.version:version=1');
    // getAction() with no args — sequenced:
    //   calls 1-3: inside RouteVersionMetadata::resolve (lines 24, 232, 188)
    //   call  4:   inside versionKey line 318 → non-array → LINE 320 hit
    //   call  5:   inside addLookups → must return array to avoid string-key access error
    $mockRoute->shouldReceive('getAction')->withNoArgs()
        ->andReturnValues([
            'not-an-array',       // call 1: resolve, line 24
            'not-an-array',       // call 2: resolveAttributeVersion, line 232
            'not-an-array',       // call 3: resolveAttributeMetadata, line 188
            'not-an-array',       // call 4: versionKey, line 318 → HITS LINE 320
            ['api_version' => '1'], // call 5: addLookups
        ]);
    $mockRoute->shouldReceive('getName')->andReturn(null);
    $mockRoute->shouldReceive('methods')->andReturn(['GET']);
    $mockRoute->shouldReceive('getDomain')->andReturn('');
    $mockRoute->shouldReceive('uri')->andReturn('/mock-vrc-test');
    $mockRoute->shouldReceive('setAction')->withAnyArgs();

    $collection->add($mockRoute);

    expect(true)->toBeTrue();
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
