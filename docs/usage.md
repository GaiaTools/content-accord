# Usage and examples

## Configure versioning

Configuration lives in `config/content-accord.php` under the `versioning` key.

```php
'versioning' => [
    'strategy' => 'uri',
    'resolver' => [
        GaiaTools\ContentAccord\Resolvers\Version\UriVersionResolver::class,
        // GaiaTools\ContentAccord\Resolvers\Version\HeaderVersionResolver::class,
        // GaiaTools\ContentAccord\Resolvers\Version\AcceptHeaderVersionResolver::class,
    ],
    'missing_strategy' => 'reject',
    'default_version' => '1',
    'fallback' => false,
    'versions' => [
        '1' => [
            'deprecated' => false,
            'sunset' => null,
            'deprecation_link' => null,
        ],
    ],
],
```

## Fluent route groups

`Route::apiVersion()` is the recommended way to declare versioned route groups. It
builds the right middleware string and URI prefix automatically based on your
configured resolver strategy, then delegates to Laravel's standard routing primitives.

```php
use Illuminate\Support\Facades\Route;

// URI strategy — prefix is added automatically (api/v1/...)
Route::apiVersion('1')
    ->prefix('api')
    ->group(function () {
        Route::get('/users', [V1\UserController::class, 'index']);
    });

// Header strategy — no URI prefix needed
Route::apiVersion('2')
    ->prefix('api')
    ->group(function () {
        Route::get('/users', [V2\UserController::class, 'index']);
    });

// Deprecation metadata via fluent chain
Route::apiVersion('1')
    ->prefix('api')
    ->deprecated()
    ->sunsetDate('2026-03-01')
    ->deprecationLink('https://docs.example.com/v1-migration')
    ->group(function () {
        Route::get('/users', [V1\UserController::class, 'index']);
    });

// Extra middleware alongside the version middleware
Route::apiVersion('2')
    ->prefix('api')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        Route::get('/users', [V2\UserController::class, 'index']);
    });
```

The raw `content-accord.version` middleware is still available for advanced or
programmatic use, but the fluent API is recommended for versioned groups.

## Route group versioning

When you use URI prefixes (`/api/v1`, `/api/v2`) and separate route groups, Laravel
itself dispatches requests to the right controller — you've already solved routing
by giving each version a distinct URL.

In that case, Content Accord is not doing the dispatch. What it provides instead:

- **Negotiated context** — resolves and validates the version from the request and
  makes it available via `apiVersion()` to any controller, middleware, or service
  that needs to know the current version.
- **Version validation** — rejects requests for versions not in your configured
  `versions` list before they reach a controller.
- **Deprecation headers** — automatically adds `Deprecation`, `Sunset`, and `Link`
  response headers for deprecated versions, with metadata declared once on the
  route group rather than in each controller.
- **Route metadata** — stamps `api_version`, `deprecated`, `sunset`, and related
  fields onto each route's action, which `php artisan api:versions` uses to
  produce its report.

If you use the **header or Accept header strategy** with a shared URI (e.g. `GET
/api/users` for all versions), Content Accord additionally handles dispatch — the
versioned route collection selects the best-matching route based on the resolved
version, including fallback to older versions when configured.

## Header strategy

Switch strategy in config:

```php
'versioning' => ['strategy' => 'header'],
```

Route definitions use the same fluent API — no URI version prefix is added:

```php
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

Request:

```http
GET /api/users
Api-Version: 2
```

## Accept header strategy

Request:

```http
GET /api/users
Accept: application/vnd.myapp.v2+json
```

Or parameter format:

```http
GET /api/users
Accept: application/vnd.myapp+json;version=2
```

## Attributes

```php
use GaiaTools\ContentAccord\Attributes\ApiVersion;
use GaiaTools\ContentAccord\Attributes\MapToVersion;
use GaiaTools\ContentAccord\Attributes\ApiDeprecate;
use GaiaTools\ContentAccord\Attributes\ApiFallback;

#[ApiVersion('2')]
#[ApiDeprecate(deprecated: true, sunset: '2026-03-01', link: 'https://docs.example.com/migration')]
class UserController
{
    public function index() {}

    #[MapToVersion('2.1')]
    public function show() {}
}

#[ApiFallback(enabled: true)]
class LegacyController
{
    public function index() {}
}
```

## Deprecation headers

```php
Route::apiVersion('1')
    ->prefix('api')
    ->deprecated()
    ->sunsetDate('2026-03-01')
    ->deprecationLink('https://docs.example.com/v1-migration')
    ->middleware(['content-accord.deprecate', 'content-accord.negotiate'])
    ->group(function () {
        Route::get('/users', [V1\UserController::class, 'index']);
    });
```

When a deprecated route is matched, the response includes:

- `Deprecation: true`
- `Sunset: <HTTP date>` (if set)
- `Link: <...>; rel="deprecation"` (if set)

## Per-route fallback

```php
Route::apiVersion('2')
    ->prefix('api')
    ->fallback()
    ->middleware(['content-accord.negotiate'])
    ->group(function () {
        Route::get('/users', [V2\UserController::class, 'index']);
    });
```

If a request targets v3 and only v2 exists for that endpoint, the v2 route will
be chosen when fallback is enabled.

## Accessing the resolved version

Use the `apiVersion()` helper anywhere in your application after the negotiate
middleware has run:

```php
use GaiaTools\ContentAccord\ValueObjects\ApiVersion;

public function index(): JsonResponse
{
    $version = apiVersion(); // ?ApiVersion
}
```

Alternatively, inject `NegotiatedContext` directly:

```php
use GaiaTools\ContentAccord\Http\NegotiatedContext;

public function index(NegotiatedContext $context): JsonResponse
{
    $version = $context->get('version'); // ?ApiVersion
}
```

## Testing helper

```php
use GaiaTools\ContentAccord\Testing\Concerns\InteractsWithApiVersion;

class ExampleTest extends TestCase
{
    use InteractsWithApiVersion;

    public function test_example()
    {
        $this->withApiVersion('2')->get('/api/users');
    }
}
```

The helper respects the configured strategy (URI/header/Accept).

## CLI

```bash
php artisan api:versions
```

Prints configured versions, deprecation metadata, and route counts.
