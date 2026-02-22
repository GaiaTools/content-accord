<?php

namespace GaiaTools\ContentAccord\Routing;

use Illuminate\Support\Facades\Route;

final class ApiVersionRegistrar
{
    public static function register(): void
    {
        Route::macro('apiVersion', function (string $version): RouteVersionBuilder {
            return new RouteVersionBuilder($version);
        });
    }
}
