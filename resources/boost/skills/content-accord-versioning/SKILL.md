---
name: content-accord-versioning
description: "Develops API versioning for Laravel applications using the gaiatools/content-accord package. Activates when installing or configuring Content Accord; declaring versioned route groups with Route::apiVersion(); configuring version resolution strategies (URI path, request header, Accept header, query string); setting up version aliases or chained resolvers; marking API versions as deprecated with sunset dates and deprecation links; enforcing sunset dates with 410 Gone responses; listening to VersionNegotiated or DeprecatedVersionAccessed events; using PHP attributes (#[ApiVersion], #[MapToVersion], #[ApiDeprecate], #[ApiFallback], #[ApiNegotiate]); accessing the resolved version via apiVersion() or NegotiatedContext; writing versioned API tests with InteractsWithApiVersion; or running the api:versions Artisan command. Make sure to use this skill whenever the user works with API versioning, version deprecation, content negotiation, or multi-version route management in Laravel, even if they don't explicitly mention Content Accord."
---

# Content Accord API Versioning

## When to use this skill

Use this skill when working with API versioning in a Laravel application that uses the `gaiatools/content-accord` package. This covers declaring versioned routes, configuring resolution strategies, marking versions as deprecated, using PHP attributes for version metadata, and writing tests.

## Installation

```bash
composer require gaiatools/content-accord
php artisan vendor:publish --tag=content-accord-config
```

## Directory and namespace conventions

Versioned controllers are typically namespaced by version:

```
app/Http/Controllers/
  Api/
    V1/UserController.php   # namespace App\Http\Controllers\Api\V1
    V2/UserController.php   # namespace App\Http\Controllers\Api\V2
```

## Configuration — config/content-accord.php

Key settings under the `versioning` key:

- `resolver`: one resolver class or an array tried in order (first non-null wins)
- `missing_strategy`: `reject` | `default` | `latest` | `require`
- `default_version`: version used when `missing_strategy` is `default`
- `fallback`: global fallback flag (bool); overridable per route group
- `aliases`: map of symbolic names to real version numbers (e.g. `['latest' => '3']`)
- `versions`: registered versions with optional deprecation metadata

```php
'versioning' => [
    'resolver' => [
        GaiaTools\ContentAccord\Resolvers\Version\UriVersionResolver::class,
    ],
    'missing_strategy' => 'reject',         // env CONTENT_ACCORD_VERSIONING_MISSING
    'default_version' => '1',
    'fallback' => false,
    'aliases' => [
        // 'latest' => '3',
        // 'stable' => '2',
    ],
    'versions' => [
        '1' => ['deprecated' => false, 'sunset' => null, 'deprecation_link' => null],
        '2' => ['deprecated' => false, 'sunset' => null, 'deprecation_link' => null],
    ],
],
```

Strategy-specific options:

```php
'strategies' => [
    'uri'    => ['prefix' => 'v', 'parameter' => 'version'],   // /api/v1/users
    'header' => ['name' => 'Api-Version'],                      // Api-Version: 1
    'accept' => ['vendor' => 'myapp'],                          // application/vnd.myapp.v1+json
    'query'  => ['parameter' => 'version'],                     // /api/users?version=2
],
```

## Declaring versioned routes — Route::apiVersion()

`Route::apiVersion()` is the recommended API. It generates the middleware string and URI prefix automatically based on the configured strategy.

```php
use Illuminate\Support\Facades\Route;

// URI strategy — registers at /api/v1/users and /api/v2/users
Route::apiVersion('1')
    ->prefix('api')
    ->group(function () {
        Route::get('/users', [\App\Http\Controllers\Api\V1\UserController::class, 'index']);
    });

Route::apiVersion('2')
    ->prefix('api')
    ->group(function () {
        Route::get('/users', [\App\Http\Controllers\Api\V2\UserController::class, 'index']);
    });
```

With header or Accept strategy, both register at `/api/users` and Content Accord selects the right route at dispatch time.

### Adding middleware alongside versioning

```php
Route::apiVersion('2')
    ->prefix('api')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        Route::get('/users', [\App\Http\Controllers\Api\V2\UserController::class, 'index']);
    });
```

### Deprecation metadata (fluent chain)

```php
Route::apiVersion('1')
    ->prefix('api')
    ->deprecated()
    ->sunsetDate('2026-03-01')
    ->deprecationLink('https://docs.example.com/v1-migration')
    ->group(function () {
        Route::get('/users', [\App\Http\Controllers\Api\V1\UserController::class, 'index']);
    });
```

Deprecated routes emit `Deprecation: true`, `Sunset: <date>`, and `Link: <url>; rel="deprecation"` response headers via the `content-accord.deprecate` middleware (included automatically by the fluent builder).

### Per-group fallback

```php
Route::apiVersion('2')
    ->prefix('api')
    ->fallback()   // requests for v3 fall back to v2
    ->group(function () {
        Route::get('/users', [\App\Http\Controllers\Api\V2\UserController::class, 'index']);
    });
```

## Middleware aliases

Applied automatically by the fluent builder, or added manually in advanced cases:

| Alias | Purpose |
|---|---|
| `content-accord.version` | Attach version metadata to the route action |
| `content-accord.negotiate` | Resolve dimensions and populate `NegotiatedContext` |
| `content-accord.deprecate` | Add `Deprecation`/`Sunset`/`Link` response headers |
| `content-accord.enforce-sunset` | Return `410 Gone` once a version's sunset date has passed |

Recommended order when used manually: `version` → `negotiate` → `deprecate` → `enforce-sunset`.

## Versioning strategies

### URI (default)

Routes registered at `/api/v1/users` — Laravel dispatches by URL.

```php
'resolver' => [GaiaTools\ContentAccord\Resolvers\Version\UriVersionResolver::class],
```

### Header

Request: `GET /api/users` with `Api-Version: 2`.

```php
'resolver' => [GaiaTools\ContentAccord\Resolvers\Version\HeaderVersionResolver::class],
```

### Accept header

Request: `Accept: application/vnd.myapp.v2+json` or `Accept: application/vnd.myapp+json;version=2`.

```php
'resolver' => [GaiaTools\ContentAccord\Resolvers\Version\AcceptHeaderVersionResolver::class],
```

### Query string

Request: `GET /api/users?version=2`. Parameter name configurable via `strategies.query.parameter`.

```php
'resolver' => [GaiaTools\ContentAccord\Resolvers\Version\QueryStringVersionResolver::class],
```

### Chained resolvers

```php
'resolver' => [
    GaiaTools\ContentAccord\Resolvers\Version\HeaderVersionResolver::class,
    GaiaTools\ContentAccord\Resolvers\Version\UriVersionResolver::class,
],
```

First non-null result wins.

## Version aliases

Map symbolic names to real version numbers so clients can send `Api-Version: latest` or `?version=stable` instead of a number. Supported by URI, header, and query-string resolvers (not Accept header).

```php
'aliases' => [
    'latest' => '3',
    'stable' => '2',
],
```

## Sunset enforcement

Add `content-accord.enforce-sunset` to route groups (or globally) alongside `content-accord.deprecate`. Once the current date exceeds the configured sunset date the middleware returns `410 Gone` instead of passing the request through.

```php
Route::apiVersion('1')
    ->prefix('api')
    ->deprecated()
    ->sunsetDate('2026-06-01')
    ->middleware(['content-accord.enforce-sunset'])
    ->group(function () {
        Route::get('/users', [\App\Http\Controllers\Api\V1\UserController::class, 'index']);
    });
```

## Lifecycle events

Two events are fired automatically — no configuration needed. Listen to them in a standard Laravel `EventServiceProvider` or with `Event::listen()`.

**`GaiaTools\ContentAccord\Events\VersionNegotiated`** — fired by `NegotiateContext` after every successful version resolution (including fallback defaults).

**`GaiaTools\ContentAccord\Events\DeprecatedVersionAccessed`** — fired by `DeprecationHeaders` when a request hits a deprecated route and a version is present in the negotiated context.

Both carry `public readonly ApiVersion $version` and `public readonly Request $request`.

```php
use GaiaTools\ContentAccord\Events\VersionNegotiated;
use GaiaTools\ContentAccord\Events\DeprecatedVersionAccessed;

Event::listen(VersionNegotiated::class, function (VersionNegotiated $event) {
    Log::info('Version negotiated', ['version' => (string) $event->version]);
});

Event::listen(DeprecatedVersionAccessed::class, function (DeprecatedVersionAccessed $event) {
    // alert, log, or increment a metric
});
```

## PHP attributes

Use attributes on controllers or methods to declare version metadata. Attribute values override fluent builder and middleware parameters.

```php
use GaiaTools\ContentAccord\Attributes\ApiVersion;
use GaiaTools\ContentAccord\Attributes\MapToVersion;
use GaiaTools\ContentAccord\Attributes\ApiDeprecate;
use GaiaTools\ContentAccord\Attributes\ApiFallback;
use GaiaTools\ContentAccord\Attributes\ApiNegotiate;

// Class-level version
#[ApiVersion('2')]
class UserController
{
    public function index() {}

    // Method-level version overrides class-level
    #[MapToVersion('2.1')]
    public function show() {}
}

// Deprecation metadata on the controller
#[ApiDeprecate(deprecated: true, sunset: '2026-03-01', link: 'https://docs.example.com/migration')]
class LegacyController {}

// Enable fallback for this controller
#[ApiFallback(enabled: true)]
class FallbackController {}

// Restrict which dimensions are negotiated for this route
#[ApiNegotiate(only: ['version'])]
class VersionOnlyController {}
```

## Accessing the resolved version

After `content-accord.negotiate` has run, use the `apiVersion()` helper:

```php
use GaiaTools\ContentAccord\ValueObjects\ApiVersion;

public function index(): JsonResponse
{
    $version = apiVersion(); // ?ApiVersion
    // $version->major(), $version->minor(), $version->toString()
}
```

Or inject `NegotiatedContext` directly:

```php
use GaiaTools\ContentAccord\Http\NegotiatedContext;

public function index(NegotiatedContext $context): JsonResponse
{
    $version = $context->get('version'); // ?ApiVersion
}
```

## Missing version strategies

| Strategy | Behaviour |
|---|---|
| `reject` | Throws `MissingVersionException` (default) |
| `default` | Uses `default_version` from config |
| `latest` | Selects the highest configured major version |
| `require` | Throws `MissingVersionException` with a list of supported versions |

## Custom dimensions (advanced)

Implement `NegotiationDimension` and register in config:

```php
use GaiaTools\ContentAccord\Dimensions\VersioningDimension;
use App\Http\Negotiation\LocaleDimension;

'dimensions' => [
    VersioningDimension::class,
    LocaleDimension::class,
],
```

Register custom dimensions in the service container so they can be resolved.

## Testing

Use `InteractsWithApiVersion` to attach versions to test requests automatically using the configured strategy:

```php
use GaiaTools\ContentAccord\Testing\Concerns\InteractsWithApiVersion;

class UserApiTest extends TestCase
{
    use InteractsWithApiVersion;

    public function test_returns_users_for_v2(): void
    {
        $this->withApiVersion('2')
            ->getJson('/api/users')
            ->assertOk();
    }
}
```

## Artisan command

```bash
# List versions with deprecation metadata and route counts
php artisan api:versions

# Also show each route (method, URI, action) grouped by version
php artisan api:versions --routes
```
