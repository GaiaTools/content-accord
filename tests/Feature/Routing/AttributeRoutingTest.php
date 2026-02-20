<?php

use GaiaTools\ContentAccord\Attributes\ApiVersion as ApiVersionAttribute;
use GaiaTools\ContentAccord\Attributes\MapToVersion;
use Illuminate\Support\Facades\Route;

#[ApiVersionAttribute('2')]
class AttributeTestController
{
    public function index(): string
    {
        return 'ok';
    }

    #[MapToVersion('2.1')]
    public function show(): string
    {
        return 'ok';
    }
}

test('attribute metadata overrides api version on routes', function () {
    config([
        'content-accord.versioning.strategy' => 'header',
        'content-accord.versioning.strategies.header.name' => 'Api-Version',
    ]);

    Route::apiVersion('2')->group(function () {
        Route::get('/users', [AttributeTestController::class, 'index']);
        Route::get('/users/{id}', [AttributeTestController::class, 'show']);
    });

    $routes = collect(app('router')->getRoutes()->getRoutes());

    $indexRoute = $routes->first(fn ($route) => $route->getAction('controller') === AttributeTestController::class . '@index');
    $showRoute = $routes->first(fn ($route) => $route->getAction('controller') === AttributeTestController::class . '@show');

    expect($indexRoute)->not->toBeNull()
        ->and($indexRoute->getAction('api_version'))->toBe('2')
        ->and($showRoute)->not->toBeNull()
        ->and($showRoute->getAction('api_version'))->toBe('2.1');
});
