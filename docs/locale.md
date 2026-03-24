# Locale negotiation

`LocaleDimension` resolves the request locale from the `Accept-Language` header
(or custom header, or query parameter), validates it against your supported list,
and stores it in `NegotiatedContext` under the key `locale`. Everything downstream
in the request — controllers, services, query scopes — can then read the resolved
locale from that single source.

## Enable the dimension

Add `LocaleDimension` to the `dimensions` array in your config. It must appear
alongside any other dimensions you use:

```php
// config/content-accord.php
use GaiaTools\ContentAccord\Dimensions\LocaleDimension;
use GaiaTools\ContentAccord\Dimensions\VersioningDimension;

'dimensions' => [
    VersioningDimension::class,
    LocaleDimension::class,
],

'locale' => [
    'resolver' => [
        GaiaTools\ContentAccord\Resolvers\Locale\AcceptLanguageLocaleResolver::class,
    ],
    'default'   => 'en',
    'supported' => ['en', 'es', 'fr'],
],
```

The middleware alias `content-accord.negotiate` runs all configured dimensions.
No extra middleware is needed specifically for locale.

## Reading the locale in a controller

Inject `NegotiatedContext` or use a helper:

```php
use GaiaTools\ContentAccord\Http\NegotiatedContext;

class ArticleController
{
    public function index(NegotiatedContext $context): JsonResponse
    {
        $locale = $context->get('locale'); // 'es'
    }
}
```

The resolved value is a plain string — the locale tag as sent by the client and
validated against your `supported` list (e.g. `'es'`, `'fr-FR'`).

## Loading localised database records

The locale string is just a value — how you use it in queries depends on your
data model. Common patterns:

### Separate translation table

A classic normalised structure: a `posts` table for shared fields, a
`post_translations` table for locale-specific fields.

```php
// PostTranslation model
// Schema: id, post_id, locale, title, body

class ArticleController
{
    public function show(NegotiatedContext $context, int $id): JsonResponse
    {
        $locale = $context->get('locale');

        $post = Post::with(['translations' => function ($query) use ($locale) {
            $query->where('locale', $locale);
        }])->findOrFail($id);

        $translation = $post->translations->first()
            ?? abort(404, "No {$locale} translation available");

        return response()->json([
            'id'    => $post->id,
            'title' => $translation->title,
            'body'  => $translation->body,
        ]);
    }
}
```

### JSON column (single-table)

If translations are stored in a JSON column on the main table:

```php
// Schema: posts.title = {"en": "Hello", "es": "Hola", "fr": "Bonjour"}

$locale = $context->get('locale');

$post = Post::findOrFail($id);
$title = $post->title[$locale] ?? $post->title['en']; // fallback to English
```

### Spatie Laravel Translatable

If you use `spatie/laravel-translatable`, pass the locale directly to the model:

```php
use Spatie\Translatable\HasTranslations;

class Article extends Model
{
    use HasTranslations;
    public array $translatable = ['title', 'body'];
}

// In the controller:
$locale = $context->get('locale');

$article = Article::findOrFail($id);
$title   = $article->getTranslation('title', $locale);
```

Or set a translation locale for the whole request (see below) so that
`$article->title` returns the right value automatically.

### Locale column (one row per locale)

When each locale is a separate row:

```php
// Schema: products.locale, products.sku, products.name, products.description

$locale = $context->get('locale');

$product = Product::where('sku', $sku)
    ->where('locale', $locale)
    ->firstOrFail();
```

## Setting Laravel's app locale

If you also use Laravel's `__()` helper or `App::getLocale()` elsewhere in the
request, align them with the negotiated locale in a middleware that runs after
`content-accord.negotiate`:

```php
// app/Http/Middleware/ApplyNegotiatedLocale.php

namespace App\Http\Middleware;

use Closure;
use GaiaTools\ContentAccord\Http\NegotiatedContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class ApplyNegotiatedLocale
{
    public function __construct(private NegotiatedContext $context) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $locale = $this->context->get('locale');

        if (is_string($locale) && $locale !== '') {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
```

Register and order it after the negotiate middleware in your route group or
global middleware stack:

```php
Route::middleware([
    'content-accord.negotiate',
    ApplyNegotiatedLocale::class,
])->group(function () {
    Route::get('/articles', [ArticleController::class, 'index']);
});
```

With this in place, `__('messages.welcome')` and `Spatie\Translatable` both
read from the same source as your query layer.

## Combining with API versioning

Locale and version are independent dimensions resolved in the same middleware
pass. Both are available through `NegotiatedContext` in the controller:

```php
public function index(NegotiatedContext $context): JsonResponse
{
    $version = $context->get('version'); // ApiVersion
    $locale  = $context->get('locale');  // 'fr'

    $articles = Article::forVersion($version->major)
        ->where('locale', $locale)
        ->get();

    return response()->json($articles);
}
```

## Per-route locale filtering

If some routes should not run locale negotiation (e.g. a health check or an
endpoint that serves all locales), use the `ApiNegotiate` attribute to skip it:

```php
use GaiaTools\ContentAccord\Attributes\ApiNegotiate;

#[ApiNegotiate(skip: ['locale'])]
class HealthController
{
    public function status(): JsonResponse { ... }
}
```

Or to run locale negotiation only, skipping version:

```php
#[ApiNegotiate(only: ['locale'])]
class TranslationController
{
    public function index(): JsonResponse { ... }
}
```

## Resolution order

The resolver chain is tried in the order configured under `locale.resolver`.
The default ships with `AcceptLanguageLocaleResolver` only, but you can stack
resolvers so clients can override via header or query parameter:

```php
// config/content-accord.php
'locale' => [
    'resolver' => [
        // Explicit override wins first
        GaiaTools\ContentAccord\Resolvers\Locale\HeaderLocaleResolver::class,
        // Then query parameter
        GaiaTools\ContentAccord\Resolvers\Locale\QueryStringLocaleResolver::class,
        // Finally standard header
        GaiaTools\ContentAccord\Resolvers\Locale\AcceptLanguageLocaleResolver::class,
    ],
    'strategies' => [
        'header' => ['name' => 'X-Locale'],
        'query'  => ['parameter' => 'locale'],
    ],
    // ...
],
```

A client can then choose:

```http
# Standard browser header
GET /api/articles
Accept-Language: fr-FR

# Explicit override header
GET /api/articles
X-Locale: es

# Query parameter (useful for testing or direct links)
GET /api/articles?locale=fr
```

## Unsupported locale

When the resolved locale is not in your `supported` list, the dimension throws
`UnsupportedLocaleException`, which renders as:

```http
HTTP/1.1 406 Not Acceptable
Content-Type: application/json

{"message": "Unsupported locale: de. Supported locales: en, es, fr"}
```

An empty `supported` array disables validation — all locales are accepted. Use
this during development or when your application can handle arbitrary locales
at the query layer.

## Missing locale

When no resolver returns a value:

- If `default` is set, it is returned as the fallback locale.
- If `default` is an empty string, `MissingLocaleException` is thrown with a
  406 response, requiring clients to always send a locale.

## Testing

Access the negotiated context directly in feature tests to assert the resolved
locale, or set the header to drive the resolution:

```php
use GaiaTools\ContentAccord\Http\NegotiatedContext;

test('returns spanish article', function () {
    $article = Article::factory()->create(['locale' => 'es']);

    $response = $this->withHeaders(['Accept-Language' => 'es'])
        ->getJson("/api/articles/{$article->id}");

    $response->assertOk();
});

test('rejects unsupported locale', function () {
    $this->withHeaders(['Accept-Language' => 'zh'])
        ->getJson('/api/articles')
        ->assertStatus(406);
});
```
