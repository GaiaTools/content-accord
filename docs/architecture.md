# Architecture and concepts

## Core concepts

### Negotiation dimensions
A *dimension* is a negotiation axis like `version`,
`locale`, `format`, or `tenant`. Each dimension implements
`NegotiationDimension` and provides:

- a unique key (stored in the negotiated context)
- a resolver (or chain of resolvers)
- validation rules for the resolved value
- a fallback strategy if nothing can be resolved

### Negotiated context
`NegotiatedContext` is a scoped container that stores resolved dimension values
for the current request. Each dimension writes to it using its key.

### Versioning dimension
The built-in `VersioningDimension` uses a `ContextResolver` to parse the API
version into an `ApiVersion` value object. It validates the resolved major version
against the configured supported versions and applies the configured
missing-version strategy (reject/default/latest/require).

### Resolvers
Resolvers turn a request into a version value:

- `UriVersionResolver` reads a route parameter or route action version
- `HeaderVersionResolver` reads a configured request header
- `AcceptHeaderVersionResolver` parses vendor media types in `Accept`
- `ChainedResolver` tries multiple resolvers and picks the first non-null result

### Route metadata
Version metadata can come from multiple sources:

- The fluent `Route::apiVersion()` builder (recommended — generates the middleware string below)
- Middleware parameters (`content-accord.version`) directly
- Attributes on controllers/methods (`ApiVersion`, `MapToVersion`)
- Config metadata for a version (deprecation settings)
- Per-route-group fallback flag (builder, middleware, or attribute)

Attributes win when conflicts occur.

## Two modes of use

Content Accord works differently depending on whether your versions share a URI.

### Separate URIs (URI prefix groups)

```
GET /api/v1/users  →  V1\UserController
GET /api/v2/users  →  V2\UserController
```

Laravel's own routing dispatches to the right controller because each version has
a distinct URL. Content Accord is not involved in that dispatch. What it adds:

- **Negotiated context** — resolves, validates, and stores the version so any
  code in the request lifecycle can read it without parsing the URL again.
- **Version validation** — rejects requests for versions absent from your
  configured `versions` list before they reach a controller.
- **Deprecation headers** — emits `Deprecation`, `Sunset`, and `Link` response
  headers declared once on the route group, not in each controller.
- **Route metadata** — stamps version and deprecation fields on each route for
  introspection tools like `php artisan api:versions`.

### Shared URI (header or Accept header strategy)

```
GET /api/users  +  Api-Version: 1  →  V1\UserController
GET /api/users  +  Api-Version: 2  →  V2\UserController
```

Multiple routes share the same URI. Content Accord additionally handles dispatch:
the versioned route collection intercepts route matching and selects the best
candidate based on the resolved version, falling back to an older version when
configured.

All of the cross-cutting benefits above apply here too.

## High-level architecture

1. The service provider registers configuration, binds the negotiated context,
   registers middleware aliases, and swaps the route collection for a
   version-aware collection.
2. The version-aware route collection uses the configured resolver to select the
   most appropriate route for the requested version.
3. Middleware attaches per-route metadata, negotiates dimension values, and adds
   deprecation headers to the response.

This separation means routing can select the right version *before* the request
reaches your controller, while the negotiated context is still available during
request handling.
