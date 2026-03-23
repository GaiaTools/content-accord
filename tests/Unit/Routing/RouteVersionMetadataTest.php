<?php

use GaiaTools\ContentAccord\Attributes\ApiVersion as ApiVersionAttr;
use GaiaTools\ContentAccord\Routing\RouteVersionMetadata;
use Illuminate\Routing\Route;

// --- non-array action defensive checks (lines 26, 190, 234) ---

test('resolve handles non-array getAction gracefully covering all defensive action checks', function () {
    $mockRoute = Mockery::mock(Route::class);
    // getAction() with no args returns non-array → triggers lines 26, 190, 234
    $mockRoute->shouldReceive('getAction')->withNoArgs()->andReturn('not-an-array');
    // resolveFromMiddleware calls getAction('middleware')
    $mockRoute->shouldReceive('getAction')->with('middleware')->andReturn([]);

    $metadata = RouteVersionMetadata::resolve($mockRoute);

    expect($metadata)->toBeArray()->toBeEmpty();
});

// --- resolveFromMiddleware edge cases ---

test('resolve handles string middleware (not array)', function () {
    $route = new Route('GET', '/test', []);
    $route->setAction([
        'middleware' => 'content-accord.version:version=1',
    ]);

    $metadata = RouteVersionMetadata::resolve($route);

    expect($metadata['version'])->toBe('1');
});

test('resolve returns empty when middleware is not array or string', function () {
    $route = new Route('GET', '/test', []);
    $route->setAction([
        'middleware' => 42, // invalid type
    ]);

    $metadata = RouteVersionMetadata::resolve($route);

    expect($metadata)->not->toHaveKey('version');
});

test('resolve skips non-string middleware entries', function () {
    $route = new Route('GET', '/test', []);
    $route->setAction([
        'middleware' => [null, 42, 'content-accord.version:version=2'],
    ]);

    $metadata = RouteVersionMetadata::resolve($route);

    expect($metadata['version'])->toBe('2');
});

test('resolve skips middleware entries that do not match version middleware names', function () {
    $route = new Route('GET', '/test', []);
    $route->setAction([
        'middleware' => ['auth:sanctum', 'throttle:60,1'],
    ]);

    $metadata = RouteVersionMetadata::resolve($route);

    expect($metadata)->not->toHaveKey('version');
});

// --- resolveAttributeMetadata edge cases ---

test('resolve returns empty attribute metadata when controller class does not exist', function () {
    $route = new Route('GET', '/test', []);
    $route->setAction([
        'controller' => 'NonExistent\\Controller@index',
    ]);

    $metadata = RouteVersionMetadata::resolve($route);

    expect($metadata)->not->toHaveKey('deprecated');
});

test('resolve returns empty attribute version when controller class does not exist', function () {
    $route = new Route('GET', '/test', []);
    $route->setAction([
        'controller' => 'NonExistent\\Controller@show',
        'middleware' => ['content-accord.version:version=3'],
    ]);

    $metadata = RouteVersionMetadata::resolve($route);

    // Version from middleware, no attribute override
    expect($metadata['version'])->toBe('3');
});

// --- resolveConfigMetadata edge cases ---

test('resolve config metadata returns empty when version cannot be parsed', function () {
    $route = new Route('GET', '/test', []);
    $route->setAction(['api_version' => 'unparseable!!!']);

    $config = [
        'versions' => ['1' => ['deprecated' => true]],
    ];

    $metadata = RouteVersionMetadata::resolve($route, $config);

    // Version key still set but no deprecated from config since parse fails
    expect($metadata)->not->toHaveKey('deprecated');
});

test('resolve config metadata returns empty when versions config is not array', function () {
    $route = new Route('GET', '/test', []);
    $route->setAction(['api_version' => '1']);

    $config = ['versions' => 'not-an-array'];

    $metadata = RouteVersionMetadata::resolve($route, $config);

    expect($metadata['version'])->toBe('1')
        ->and($metadata)->not->toHaveKey('deprecated');
});

// --- parseControllerAction edge cases ---

#[ApiVersionAttr('5')]
class RouteVersionMetadataInvokableController
{
    public function __invoke(): string
    {
        return 'ok';
    }
}

test('resolve uses __invoke when controller has no @ separator', function () {
    $route = new Route('GET', '/test', []);
    $route->setAction(['controller' => RouteVersionMetadataInvokableController::class]);

    $metadata = RouteVersionMetadata::resolve($route);

    expect($metadata['version'])->toBe('5');
});

// --- action is not an array edge cases ---

test('resolve handles non-array action gracefully', function () {
    $route = new Route('GET', '/test', fn () => 'ok');
    // Routes with closure actions have string 'Closure' in action
    // Simulate by accessing a minimal scenario — just ensure no crash

    $metadata = RouteVersionMetadata::resolve($route);

    expect($metadata)->toBeArray();
});

// --- fallback from config ---

test('resolve picks up fallback from config when not set in action or middleware', function () {
    $route = new Route('GET', '/test', []);
    $route->setAction(['api_version' => '2']);

    $config = [
        'fallback' => true,
        'versions' => ['2' => []],
    ];

    $metadata = RouteVersionMetadata::resolve($route, $config);

    expect($metadata['fallback'])->toBeTrue();
});
