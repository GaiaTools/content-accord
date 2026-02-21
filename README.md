# Content Accord

Content Accord is a Laravel package for API versioning with composable strategies and a generic negotiation layer. It supports URI, header, and Accept header versioning behind a single fluent API and prepares your application for future negotiation dimensions (locale, format, tenant).

## Features

- URI, custom header, or Accept header versioning
- Optional resolver chaining (try multiple strategies in order)
- Configurable missing-version behavior
- Per-route-group fallback when the requested version is missing
- Deprecation headers with sunset and documentation links
- Attribute-driven version metadata on controllers and methods
- Generic negotiation foundation for future dimensions

## Requirements

- PHP 8.3+
- Laravel 12+ (Laravel 11 and 13 are supported via constraints)

## Installation

```bash
composer require gaiatools/content-accord
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=content-accord-config
```

## Configuration

The main configuration lives in `config/content-accord.php` under the `versioning` key.

Key settings:

- `dimensions`: array of dimension services to negotiate
- `strategy`: `uri`, `header`, or `accept`
- `resolver`: custom resolver class/binding for versioning
- `chain`: array of strategies to try in order
- `missing_strategy`: `reject`, `default`, `latest`, or `require`
- `default_version`: used when missing strategy is `default`
- `fallback`: global default for version fallback
- `versions`: registered versions and deprecation metadata

## Usage

### Fluent Route Groups (Recommended)

Use `Route::apiVersion()` to declare versioned route groups. The URI prefix is
managed automatically based on your configured resolver strategy.

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

With the URI strategy (default), the above registers at `/api/v1/users` and
`/api/v2/users`. With header or Accept strategies, both register at `/api/users`
and Content Accord selects the right route at dispatch time.

Deprecation metadata is a fluent chain:

```php
Route::apiVersion('1')
    ->prefix('api')
    ->deprecated()
    ->sunsetDate('2026-03-01')
    ->deprecationLink('https://docs.example.com/v1-migration')
    ->middleware(['content-accord.negotiate'])
    ->group(function () {
        Route::get('/users', [V1\UserController::class, 'index']);
    });
```

### Header Strategy

```php
// config/content-accord.php
'versioning' => ['strategy' => 'header'],
```

```php
Route::apiVersion('1')
    ->prefix('api')
    ->middleware(['content-accord.negotiate'])
    ->group(function () {
        Route::get('/users', [V1\UserController::class, 'index']);
    });
```

Requests:

```http
GET /api/users
Api-Version: 1
```

### Accept Header Strategy

```http
GET /api/users
Accept: application/vnd.myapp.v1+json
```

### Custom Dimensions and Resolvers

Override the negotiated dimensions or the resolver implementation:

```php
use GaiaTools\ContentAccord\Dimensions\VersioningDimension;
use App\Http\Negotiation\LocaleDimension;

'dimensions' => [
    VersioningDimension::class,
    LocaleDimension::class,
],

'versioning' => [
    'resolver' => [
        App\Http\Negotiation\CustomVersionResolver::class,
        GaiaTools\ContentAccord\Resolvers\Version\HeaderVersionResolver::class,
    ],
],
```

Register any custom dimensions/resolvers in the container so they can be resolved.

### Missing Version Behavior

Configure what happens when a request has no version:

```php
'missing_strategy' => 'default',
'default_version' => '1',
```

### Fallback Behavior

Enable fallback globally or per group:

```php
// config
'fallback' => false,

// route group override
Route::apiVersion('2')
    ->prefix('api')
    ->fallback()
    ->middleware(['content-accord.negotiate'])
    ->group(function () {
        Route::get('/users', [V2\UserController::class, 'index']);
    });
```

If a request targets v3 but only v2 exists for that endpoint, the v2 route will be selected when fallback is enabled.

## Attributes

Add version metadata on controllers or methods:

```php
use GaiaTools\ContentAccord\Attributes\ApiVersion;
use GaiaTools\ContentAccord\Attributes\MapToVersion;

#[ApiVersion('2')]
class UserController
{
    public function index() {}

    #[MapToVersion('2.1')]
    public function show() {}
}
```

Method-level attributes take precedence over class-level attributes. Attribute versions override the group version in route metadata. Mismatches are logged in local/testing environments.

## Deprecation Headers

Mark version groups as deprecated and optionally add sunset dates and docs links:

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

The `Deprecation`, `Sunset`, and `Link` headers are added automatically when deprecation metadata is present.

## Accessing the Negotiated Version

Use the `apiVersion()` helper in controllers or anywhere after the negotiate
middleware has run:

```php
use GaiaTools\ContentAccord\ValueObjects\ApiVersion;

public function index(): JsonResponse
{
    $version = apiVersion(); // ?ApiVersion
}
```

Or inject `NegotiatedContext` directly:

```php
use GaiaTools\ContentAccord\Http\NegotiatedContext;

$version = app(NegotiatedContext::class)->get('version');
```

## Testing Utilities

Use the testing helper to attach API versions to test requests:

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

The helper respects the configured strategy (URI, header, or Accept).

## Artisan Command

List configured versions and route counts:

```bash
php artisan api:versions
```

## License

MIT
