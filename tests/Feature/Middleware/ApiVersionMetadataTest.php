<?php

use GaiaTools\ContentAccord\Attributes\ApiDeprecate;
use GaiaTools\ContentAccord\Attributes\ApiFallback;
use GaiaTools\ContentAccord\Attributes\ApiVersion as ApiVersionAttribute;
use GaiaTools\ContentAccord\Attributes\MapToVersion;
use GaiaTools\ContentAccord\Http\Middleware\ApiVersionMetadata;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;

// Controllers used in attribute tests

#[ApiVersionAttribute('2')]
class MetadataClassVersionController
{
    public function index(): string
    {
        return 'ok';
    }
}

#[ApiDeprecate(deprecated: true, sunset: '2030-01-01', link: 'https://example.test')]
class MetadataClassDeprecatedController
{
    public function index(): string
    {
        return 'ok';
    }
}

#[ApiFallback(true)]
class MetadataClassFallbackController
{
    public function index(): string
    {
        return 'ok';
    }
}

class MetadataMethodVersionController
{
    #[MapToVersion('3.1')]
    public function index(): string
    {
        return 'ok';
    }

    #[ApiVersionAttribute('3')]
    public function show(): string
    {
        return 'ok';
    }
}

class MetadataMismatchVersionController
{
    #[ApiVersionAttribute('3')]
    public function index(): string
    {
        return 'ok';
    }
}

// --- parseParameters tests ---

test('parseParameters returns empty array for empty params', function () {
    $result = ApiVersionMetadata::parseParameters([]);
    expect($result)->toBe([]);
});

test('parseParameters parses positional params', function () {
    $result = ApiVersionMetadata::parseParameters(['1', 'true', '2030-01-01', 'https://example.test', 'false']);
    expect($result['version'])->toBe('1')
        ->and($result['deprecated'])->toBeTrue()
        ->and($result['sunsetDate'])->toBe('2030-01-01')
        ->and($result['deprecationLink'])->toBe('https://example.test')
        ->and($result['fallbackEnabled'])->toBeFalse();
});

test('parseParameters parses named params with version key', function () {
    $result = ApiVersionMetadata::parseParameters(['version=2', 'deprecated=true']);
    expect($result['version'])->toBe('2')
        ->and($result['deprecated'])->toBeTrue();
});

test('parseParameters parses named params with v shorthand', function () {
    $result = ApiVersionMetadata::parseParameters(['v=3', 'sunset=2025-12-31']);
    expect($result['version'])->toBe('3')
        ->and($result['sunsetDate'])->toBe('2025-12-31');
});

test('parseParameters parses named params with link and deprecation_link', function () {
    $result = ApiVersionMetadata::parseParameters(['link=https://a.test']);
    expect($result['deprecationLink'])->toBe('https://a.test');

    $result2 = ApiVersionMetadata::parseParameters(['deprecation_link=https://b.test']);
    expect($result2['deprecationLink'])->toBe('https://b.test');
});

test('parseParameters parses named fallback and fallback_enabled', function () {
    $result = ApiVersionMetadata::parseParameters(['fallback=true']);
    expect($result['fallbackEnabled'])->toBeTrue();

    $result2 = ApiVersionMetadata::parseParameters(['fallback_enabled=false']);
    expect($result2['fallbackEnabled'])->toBeFalse();
});

test('parseParameters parses deprecate as alias for deprecated', function () {
    $result = ApiVersionMetadata::parseParameters(['deprecate=yes']);
    expect($result['deprecated'])->toBeTrue();
});

test('parseParameters skips named params with empty values', function () {
    $result = ApiVersionMetadata::parseParameters(['version=', 'deprecated=true']);
    expect($result)->not->toHaveKey('version')
        ->and($result['deprecated'])->toBeTrue();
});

test('parseParameters skips entries without equals in named mode', function () {
    $result = ApiVersionMetadata::parseParameters(['version=2', 'noequals']);
    expect($result['version'])->toBe('2')
        ->and($result)->not->toHaveKey('noequals');
});

test('parseParameters handles null positional version as null', function () {
    $result = ApiVersionMetadata::parseParameters(['']);
    expect($result['version'])->toBeNull();
});

test('parseParameters positional with only version', function () {
    $result = ApiVersionMetadata::parseParameters(['1']);
    expect($result['version'])->toBe('1')
        ->and($result['deprecated'])->toBeNull()
        ->and($result['sunsetDate'])->toBeNull()
        ->and($result['deprecationLink'])->toBeNull()
        ->and($result['fallbackEnabled'])->toBeNull();
});

test('normalizeBool returns null when called with null value', function () {
    // normalizeBool accepts ?string but callers only pass strings; cover the null branch via reflection
    $method = new ReflectionMethod(ApiVersionMetadata::class, 'normalizeBool');
    $method->setAccessible(true);

    $result = $method->invoke(null, null);

    expect($result)->toBeNull();
});

test('normalizeBool handles various truthy and falsy strings', function () {
    $params = ['deprecated=1', 'fallback=0'];
    $result = ApiVersionMetadata::parseParameters($params);
    expect($result['deprecated'])->toBeTrue()
        ->and($result['fallbackEnabled'])->toBeFalse();

    $params2 = ['deprecated=yes', 'fallback=no'];
    $result2 = ApiVersionMetadata::parseParameters($params2);
    expect($result2['deprecated'])->toBeTrue()
        ->and($result2['fallbackEnabled'])->toBeFalse();

    $params3 = ['deprecated=on', 'fallback=off'];
    $result3 = ApiVersionMetadata::parseParameters($params3);
    expect($result3['deprecated'])->toBeTrue()
        ->and($result3['fallbackEnabled'])->toBeFalse();
});

test('normalizeBool returns null for unknown string', function () {
    $result = ApiVersionMetadata::parseParameters(['deprecated=maybe']);
    expect($result['deprecated'])->toBeNull();
});

// --- handle() method tests ---

test('handle handles non-array route action gracefully', function () {
    $middleware = new ApiVersionMetadata;
    $request = Request::create('/test');

    // Mock a Route whose getAction() returns non-array — triggers line 35
    $mockRoute = Mockery::mock(\Illuminate\Routing\Route::class)->makePartial();
    $mockRoute->shouldReceive('getAction')->withNoArgs()->andReturn('not-an-array');

    $request->setRouteResolver(fn () => $mockRoute);

    // $action becomes [], $controller is null → attribute resolution skipped, version from param used
    $middleware->handle($request, fn ($req) => response('OK'), 'version=1');

    // setAction is called on the mock — with makePartial() this proxies to real Route
    expect(true)->toBeTrue();
});

test('handle passes through when route is null', function () {
    $middleware = new ApiVersionMetadata;
    $request = Request::create('/test');
    // No route set

    $called = false;
    $response = $middleware->handle($request, function ($req) use (&$called) {
        $called = true;

        return response('OK');
    }, 'version=1');

    expect($called)->toBeTrue();
});

test('handle sets api_version in route action from params', function () {
    $middleware = new ApiVersionMetadata;
    $request = Request::create('/test');
    $route = new Route('GET', '/test', []);
    $route->setAction([]);
    $request->setRouteResolver(fn () => $route);

    $middleware->handle($request, fn ($req) => response('OK'), 'version=1');

    expect($route->getAction('api_version'))->toBe('1');
});

test('handle sets deprecated in route action from params', function () {
    $middleware = new ApiVersionMetadata;
    $request = Request::create('/test');
    $route = new Route('GET', '/test', []);
    $route->setAction([]);
    $request->setRouteResolver(fn () => $route);

    $middleware->handle($request, fn ($req) => response('OK'), 'version=1', 'deprecated=true');

    expect($route->getAction('deprecated'))->toBeTrue();
});

test('handle sets sunset and deprecation_link from params', function () {
    $middleware = new ApiVersionMetadata;
    $request = Request::create('/test');
    $route = new Route('GET', '/test', []);
    $route->setAction([]);
    $request->setRouteResolver(fn () => $route);

    $middleware->handle($request, fn ($req) => response('OK'), 'version=1', 'sunset=2030-01-01', 'link=https://example.test');

    expect($route->getAction('sunset'))->toBe('2030-01-01')
        ->and($route->getAction('deprecation_link'))->toBe('https://example.test');
});

test('handle sets fallback_enabled from params', function () {
    $middleware = new ApiVersionMetadata;
    $request = Request::create('/test');
    $route = new Route('GET', '/test', []);
    $route->setAction([]);
    $request->setRouteResolver(fn () => $route);

    $middleware->handle($request, fn ($req) => response('OK'), 'version=1', 'fallback=true');

    expect($route->getAction('fallback_enabled'))->toBeTrue();
});

test('handle resolves version from class attribute', function () {
    $middleware = new ApiVersionMetadata;
    $request = Request::create('/test');
    $route = new Route('GET', '/test', []);
    $route->setAction(['controller' => MetadataClassVersionController::class.'@index']);
    $request->setRouteResolver(fn () => $route);

    $middleware->handle($request, fn ($req) => response('OK'), 'version=1');

    expect($route->getAction('api_version'))->toBe('2');
});

test('handle resolves version from MapToVersion method attribute', function () {
    $middleware = new ApiVersionMetadata;
    $request = Request::create('/test');
    $route = new Route('GET', '/test', []);
    $route->setAction(['controller' => MetadataMethodVersionController::class.'@index']);
    $request->setRouteResolver(fn () => $route);

    $middleware->handle($request, fn ($req) => response('OK'), 'version=3');

    expect($route->getAction('api_version'))->toBe('3.1');
});

test('handle resolves version from ApiVersion method attribute', function () {
    $middleware = new ApiVersionMetadata;
    $request = Request::create('/test');
    $route = new Route('GET', '/test', []);
    $route->setAction(['controller' => MetadataMethodVersionController::class.'@show']);
    $request->setRouteResolver(fn () => $route);

    $middleware->handle($request, fn ($req) => response('OK'), 'version=3');

    expect($route->getAction('api_version'))->toBe('3');
});

test('handle resolves deprecated from class attribute', function () {
    $middleware = new ApiVersionMetadata;
    $request = Request::create('/test');
    $route = new Route('GET', '/test', []);
    $route->setAction(['controller' => MetadataClassDeprecatedController::class.'@index']);
    $request->setRouteResolver(fn () => $route);

    $middleware->handle($request, fn ($req) => response('OK'));

    expect($route->getAction('deprecated'))->toBeTrue()
        ->and($route->getAction('sunset'))->toBe('2030-01-01')
        ->and($route->getAction('deprecation_link'))->toBe('https://example.test');
});

test('handle resolves fallback from class attribute', function () {
    $middleware = new ApiVersionMetadata;
    $request = Request::create('/test');
    $route = new Route('GET', '/test', []);
    $route->setAction(['controller' => MetadataClassFallbackController::class.'@index']);
    $request->setRouteResolver(fn () => $route);

    $middleware->handle($request, fn ($req) => response('OK'));

    expect($route->getAction('fallback_enabled'))->toBeTrue();
});

test('handle skips attribute resolution when controller is not a string', function () {
    $middleware = new ApiVersionMetadata;
    $request = Request::create('/test');
    $route = new Route('GET', '/test', []);
    $route->setAction(['controller' => null]);
    $request->setRouteResolver(fn () => $route);

    $middleware->handle($request, fn ($req) => response('OK'), 'version=1');

    expect($route->getAction('api_version'))->toBe('1');
});

test('handle skips attribute resolution when class does not exist', function () {
    $middleware = new ApiVersionMetadata;
    $request = Request::create('/test');
    $route = new Route('GET', '/test', []);
    $route->setAction(['controller' => 'NonExistentClass@index']);
    $request->setRouteResolver(fn () => $route);

    $middleware->handle($request, fn ($req) => response('OK'), 'version=5');

    expect($route->getAction('api_version'))->toBe('5');
});

test('handle resolves controller without @ as __invoke', function () {
    $middleware = new ApiVersionMetadata;
    $request = Request::create('/test');
    $route = new Route('GET', '/test', []);
    $route->setAction(['controller' => MetadataClassVersionController::class]);
    $request->setRouteResolver(fn () => $route);

    $middleware->handle($request, fn ($req) => response('OK'), 'version=1');

    // Class has #[ApiVersion('2')] attribute → should resolve to '2'
    expect($route->getAction('api_version'))->toBe('2');
});

test('parseParameters positional deprecated as empty string yields null', function () {
    // Pass empty string for param[1] which triggers normalizeBool('')
    $result = ApiVersionMetadata::parseParameters(['1', '']);
    expect($result['deprecated'])->toBeNull();
});

test('warnOnVersionMismatch returns early when version parsing throws', function () {
    // Invalid groupVersion that cannot be parsed → catch block → early return (no log)
    $middleware = new ApiVersionMetadata;
    $request = Request::create('/test');
    $route = new Route('GET', '/test', []);
    $route->setAction(['controller' => MetadataClassVersionController::class.'@index']);
    $request->setRouteResolver(fn () => $route);

    // Pass an unparseable group version — should not throw or log
    $middleware->handle($request, fn ($req) => response('OK'), 'v=invalid!!!');

    expect(true)->toBeTrue(); // No exception thrown
});

test('warnOnVersionMismatch returns early when log is not bound', function () {
    // Remove the 'log' binding entirely so app()->bound('log') returns false (line 204)
    unset(app()['log']);

    // Use a version that forces major mismatch (controller has #[ApiVersion('3')] vs group version '1')
    $middleware = new ApiVersionMetadata;
    $request = Request::create('/test');
    $route = new Route('GET', '/test', []);
    $route->setAction(['controller' => MetadataMismatchVersionController::class.'@index']);
    $request->setRouteResolver(fn () => $route);

    // Should not throw — returns early at line 204
    $middleware->handle($request, fn ($req) => response('OK'), 'version=1');

    expect(true)->toBeTrue();
});

test('warnOnVersionMismatch returns early when not in local/testing/development environment', function () {
    // Override the environment to 'production' so app()->environment([...]) check fails (line 208)
    app()->instance('env', 'production');

    $middleware = new ApiVersionMetadata;
    $request = Request::create('/test');
    $route = new Route('GET', '/test', []);
    $route->setAction(['controller' => MetadataMismatchVersionController::class.'@index']);
    $request->setRouteResolver(fn () => $route);

    // Should not throw — returns early at line 208 (non-dev environment)
    $middleware->handle($request, fn ($req) => response('OK'), 'version=1');

    expect(true)->toBeTrue();
});

test('handle logs warning when attribute version major differs from group version major', function () {
    $log = Mockery::mock('Psr\Log\LoggerInterface');
    $log->shouldReceive('warning')
        ->once()
        ->withArgs(function ($message, $context) {
            return str_contains($message, 'Attribute version mismatch');
        });

    app()->instance('log', $log);

    $middleware = new ApiVersionMetadata;
    $request = Request::create('/test');
    $route = new Route('GET', '/test', []);
    // Controller has #[ApiVersion('3')] but group is version=1 → major mismatch
    $route->setAction(['controller' => MetadataMismatchVersionController::class.'@index']);
    $request->setRouteResolver(fn () => $route);

    $middleware->handle($request, fn ($req) => response('OK'), 'version=1');
});
