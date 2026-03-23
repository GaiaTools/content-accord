<?php

use GaiaTools\ContentAccord\Commands\ListApiVersionsCommand;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

class CommandTestController
{
    public function index(): string
    {
        return 'ok';
    }
}

test('api versions command outputs message when no versions configured', function () {
    config(['content-accord.versioning.versions' => []]);

    Artisan::call('api:versions');

    expect(Artisan::output())->toContain('No API versions configured.');
});

test('api versions command lists routes per version with --routes flag', function () {
    config([
        'content-accord.versioning.versions' => [
            '1' => ['deprecated' => false, 'sunset' => null, 'deprecation_link' => null],
        ],
    ]);

    Route::middleware('content-accord.version:1')->group(function () {
        Route::get('/widgets', [CommandTestController::class, 'index']);
    });

    Artisan::call('api:versions', ['--routes' => true]);

    $output = Artisan::output();

    // Route uris in Laravel don't carry a leading slash
    expect($output)->toContain('Version 1 routes')
        ->and($output)->toContain('widgets');
});

test('api versions command --routes flag skips versions with no matching routes', function () {
    config([
        'content-accord.versioning.versions' => [
            '1' => ['deprecated' => false],
            '2' => ['deprecated' => false],
        ],
    ]);

    // Only register routes for v1
    Route::middleware('content-accord.version:1')->group(function () {
        Route::get('/things', [CommandTestController::class, 'index']);
    });

    Artisan::call('api:versions', ['--routes' => true]);

    $output = Artisan::output();

    expect($output)->toContain('Version 1 routes')
        ->and($output)->not->toContain('Version 2 routes');
});

test('listRoutesByVersion catch block skips routes with unparseable version string', function () {
    // Call the private listRoutesByVersion directly via reflection to bypass
    // countRoutesByVersion (which has no try/catch and would throw first).

    config(['content-accord.versioning.versions' => ['1' => []]]);

    // A route whose api_version cannot be parsed by ApiVersion::parse
    $badRoute = new Illuminate\Routing\Route('GET', '/bad', fn () => 'ok');
    $badAction = $badRoute->getAction();
    $badAction['api_version'] = 'not-a-version!!!';
    $badRoute->setAction($badAction);

    $collection = new RouteCollection;
    $collection->add($badRoute);

    $mockRouter = Mockery::mock(Router::class);
    $mockRouter->shouldReceive('getRoutes')->andReturn($collection);

    $command = new ListApiVersionsCommand;

    $method = new ReflectionMethod($command, 'listRoutesByVersion');
    $method->setAccessible(true);
    // Passes without throwing — invalid version is caught and skipped (lines 75-76)
    $method->invoke($command, $mockRouter, ['1' => []]);

    expect(true)->toBeTrue();
});

test('api versions command lists configured versions with route counts', function () {
    config([
        'content-accord.versioning.versions' => [
            '1' => [
                'deprecated' => false,
                'sunset' => null,
                'deprecation_link' => null,
            ],
            '2' => [
                'deprecated' => true,
                'sunset' => '2030-01-01',
                'deprecation_link' => 'https://example.test/migrate',
            ],
        ],
    ]);

    Route::middleware('content-accord.version:1')->group(function () {
        Route::get('/users', [CommandTestController::class, 'index']);
    });

    Route::middleware('content-accord.version:2')->group(function () {
        Route::get('/users', [CommandTestController::class, 'index']);
        Route::get('/posts', [CommandTestController::class, 'index']);
    });

    Artisan::call('api:versions');

    $output = Artisan::output();

    expect($output)->toContain('1')
        ->and($output)->toContain('2')
        ->and($output)->toContain('yes')
        ->and($output)->toContain('no');
});
