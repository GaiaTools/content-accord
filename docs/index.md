# Content Accord

Content Accord is a Laravel package that adds API versioning through a generic
content negotiation layer. It lets you declare versions on route groups or
controllers, resolve versions from URI/header/Accept, and attach deprecation and
fallback metadata. The architecture is intentionally dimension-based so you can
extend negotiation beyond versioning later (locale, format, tenant).

## What it is

- A version negotiation layer for Laravel routes
- A route collection that picks the best matching versioned route
- A middleware pipeline that attaches metadata, negotiates context, and adds
  deprecation headers
- A set of resolvers for URI, header, or Accept header strategies

## Quick start

1. Install and publish config:

```bash
composer require gaiatools/content-accord
php artisan vendor:publish --tag=content-accord-config
```

2. Declare versioned route groups with the fluent API:

```php
use Illuminate\Support\Facades\Route;

Route::apiVersion('1')
    ->prefix('api')
    ->middleware(['content-accord.negotiate'])
    ->group(function () {
        Route::get('/users', [V1\UserController::class, 'index']);
    });

Route::apiVersion('2')
    ->prefix('api')
    ->middleware(['content-accord.negotiate'])
    ->group(function () {
        Route::get('/users', [V2\UserController::class, 'index']);
    });
```

With the URI strategy (default), the prefix is added automatically — the routes
above register at `/api/v1/users` and `/api/v2/users` respectively.

3. Choose how versions are resolved (URI/header/Accept) in `config/content-accord.php`.

## Documentation map

- Architecture and concepts: `docs/architecture.md`
- Middleware pipeline: `docs/middleware.md`
- Resolution and routing behavior: `docs/resolution.md`
- Usage and examples: `docs/usage.md`
