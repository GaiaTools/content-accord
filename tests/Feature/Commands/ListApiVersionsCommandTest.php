<?php

use GaiaTools\ContentAccord\Commands\ListApiVersionsCommand;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

class CommandTestController
{
    public function index(): string
    {
        return 'ok';
    }
}

test('api list command outputs message when no versions configured and --summary used', function () {
    config(['content-accord.versioning.versions' => []]);

    Artisan::call('api:list', ['--summary' => true]);

    expect(Artisan::output())->toContain('No API versions configured.');
});

test('api list command shows version column in route output', function () {
    config([
        'content-accord.versioning.versions' => [
            '1' => ['deprecated' => false, 'sunset' => null, 'deprecation_link' => null],
        ],
    ]);

    Route::middleware('content-accord.version:1')->group(function () {
        Route::get('/widgets', [CommandTestController::class, 'index']);
    });

    Artisan::call('api:list');

    $output = Artisan::output();

    expect($output)->toContain('v1')
        ->and($output)->toContain('widgets');
});

test('api list command --summary shows version table with deprecation metadata', function () {
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

    Artisan::call('api:list', ['--summary' => true]);

    $output = Artisan::output();

    expect($output)->toContain('1')
        ->and($output)->toContain('2')
        ->and($output)->toContain('yes')
        ->and($output)->toContain('no');
});

test('api list command --all includes unversioned routes with empty version column', function () {
    config([
        'content-accord.versioning.versions' => [
            '1' => ['deprecated' => false],
        ],
    ]);

    // Versioned route
    Route::middleware('content-accord.version:1')->group(function () {
        Route::get('/things', [CommandTestController::class, 'index']);
    });

    // Unversioned route (no content-accord middleware)
    Route::get('/health', [CommandTestController::class, 'index']);

    Artisan::call('api:list', ['--all' => true]);

    $output = Artisan::output();

    expect($output)->toContain('v1')
        ->and($output)->toContain('things')
        ->and($output)->toContain('health');
});

test('api list command only shows versioned routes by default', function () {
    config([
        'content-accord.versioning.versions' => [
            '1' => ['deprecated' => false],
        ],
    ]);

    Route::middleware('content-accord.version:1')->group(function () {
        Route::get('/things', [CommandTestController::class, 'index']);
    });

    Route::get('/health', [CommandTestController::class, 'index']);

    Artisan::call('api:list');

    $output = Artisan::output();

    expect($output)->toContain('things')
        ->and($output)->not->toContain('health');
});

test('api list command routes output includes version in json', function () {
    config([
        'content-accord.versioning.versions' => [
            '1' => ['deprecated' => false],
        ],
    ]);

    Route::middleware('content-accord.version:1')->group(function () {
        Route::get('/widgets', [CommandTestController::class, 'index']);
    });

    Artisan::call('api:list', ['--json' => true]);

    $json = json_decode(Artisan::output(), true);

    $versioned = collect($json)->firstWhere('uri', 'widgets');

    expect($versioned)->not->toBeNull()
        ->and($versioned['version'])->toBe('v1');
});
