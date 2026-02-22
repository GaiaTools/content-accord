---
name: content-accord-versioning
description: Add API versioning to Laravel applications using Content Accord — including route groups, versioning strategies, deprecation headers, attributes, and testing helpers.
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

- `strategy`: `uri` (default), `header`, or `accept`
- `resolver`: one resolver class or an array tried in order (first non-null wins)
- `missing_strategy`: `reject` | `default` | `latest` | `require`
- `default_version`: version used when `missing_strategy` is `default`
- `fallback`: global fallback flag (bool); overridable per route group
- `versions`: registered versions with optional deprecation metadata

```php
'versioning' => [
    'strategy' => 'uri',                    // env CONTENT_ACCORD_VERSIONING_STRATEGY
    'resolver' => [
        GaiaTools\ContentAccord\Resolvers\Version\UriVersionResolver::class,
    ],
    'missing_strategy' => 'reject',         // env CONTENT_ACCORD_VERSIONING_MISSING
    'default_version' => '1',
    'fallback' => false,
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

The three middleware aliases (applied automatically by the fluent builder, or manually in advanced cases):

| Alias | Purpose |
|---|---|
| `content-accord.version` | Attach version metadata to the route action |
| `content-accord.negotiate` | Resolve dimensions and populate `NegotiatedContext` |
| `content-accord.deprecate` | Add `Deprecation`/`Sunset`/`Link` response headers |

Recommended order when used manually: `version` → `negotiate` → `deprecate`.

## Versioning strategies

### URI (default)

```php
'versioning' => ['strategy' => 'uri'],
```

Routes registered at `/api/v1/users` — Laravel dispatches by URL.

### Header

```php
'versioning' => ['strategy' => 'header'],
```

Request: `GET /api/users` with `Api-Version: 2` — Content Accord selects the route.

### Accept header

```php
'versioning' => ['strategy' => 'accept'],
```

Request: `Accept: application/vnd.myapp.v2+json` or `Accept: application/vnd.myapp+json;version=2`.

### Chained resolvers

```php
'versioning' => [
    'resolver' => [
        GaiaTools\ContentAccord\Resolvers\Version\HeaderVersionResolver::class,
        GaiaTools\ContentAccord\Resolvers\Version\UriVersionResolver::class,
    ],
],
```

First non-null result wins.

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
php artisan api:versions
```

Lists all configured versions, deprecation metadata, sunset dates, and route counts per version.
