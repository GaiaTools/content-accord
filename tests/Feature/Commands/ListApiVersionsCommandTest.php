<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

class CommandTestController
{
    public function index(): string
    {
        return 'ok';
    }
}

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
