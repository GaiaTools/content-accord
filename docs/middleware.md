# Middleware pipeline

Content Accord uses three middleware entries that are typically applied together
on API route groups. The recommended order is:

1. `content-accord.version` (ApiVersionMetadata)
2. `content-accord.negotiate` (NegotiateContext)
3. `content-accord.deprecate` (DeprecationHeaders)

## 1) ApiVersionMetadata

Purpose: attach versioning metadata to the route action, and merge in attribute
metadata when present.

Inputs:

- middleware parameters (positional or named)
- controller/method attributes (`ApiVersion`, `MapToVersion`, `ApiDeprecate`,
  `ApiFallback`)

Behavior notes:

- Attribute versions override middleware parameters.
- Attribute deprecation/fallback metadata overrides middleware parameters.
- When a version attribute conflicts with the middleware version, a warning is
  logged in local/testing environments.

In normal use the `Route::apiVersion()` fluent builder generates and applies this
middleware for you. The raw form is available for advanced or programmatic cases:

```php
Route::middleware([
    'content-accord.version:version=2,deprecated=true,sunset=2026-03-01,link=https://docs.example.com/migration,fallback=true',
    'content-accord.negotiate',
    'content-accord.deprecate',
])->group(function () {
    // routes
});
```

## 2) NegotiateContext

Purpose: resolve all configured negotiation dimensions and store the values in
`NegotiatedContext` for the request lifecycle.

Process:

- For each dimension, call its resolver
- If the resolver returns null, call the dimension fallback
- Validate the final value and store it in `NegotiatedContext`

You can narrow which dimensions run using the `ApiNegotiate` attribute or route
defaults (`content_accord.only` / `content_accord.skip`).

## 3) DeprecationHeaders

Purpose: add `Deprecation`, `Sunset`, and `Link` headers when a version is
marked as deprecated.

Inputs:

- Route metadata resolved by `RouteVersionMetadata`
- Config metadata for the resolved major version

Headers are only added if the route is flagged as deprecated.
