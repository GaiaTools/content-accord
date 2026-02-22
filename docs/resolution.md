# Resolution and routing

This package resolves versions in two places:

- **Routing time**: the version-aware route collection picks the best route
  for the request version.
- **Request time**: the negotiation middleware resolves dimensions and stores
  them in `NegotiatedContext`.

## Request version resolution

A resolver returns an `ApiVersion` or null. Built-in strategies:

- URI: read a route parameter (default `version`) or route action `api_version`
- Header: read a configured header (default `Api-Version`)
- Accept: parse `application/vnd.{vendor}.v{version}` or
  `application/vnd.{vendor}+{format};version={version}`

You can configure a single resolver or an ordered array of resolvers. When an
array is provided, the first non-null result wins.

## Missing version strategies

If no version is resolved, the missing strategy decides what happens:

- `reject`: throw `MissingVersionException`
- `default`: use `default_version` (or throw if missing)
- `latest`: choose the highest configured major version
- `require`: throw `MissingVersionException` with a supported versions list

## Versioned route selection

When multiple routes match the request path and method, the version-aware route
collection applies version selection:

1. Gather all versioned routes (routes with an `api_version` action).
2. Resolve the requested version using the configured resolver.
3. Ensure the requested major version is supported.
4. Select the best exact match:
   - same major, highest minor wins
5. If no exact match:
   - optionally fall back to the highest lower major marked with `fallback`

Fallback is controlled by:

- per-route-group metadata (`fallback=true`), or
- the global `versioning.fallback` config value

If no match is found and no fallback is available, routing continues to normal
fallback handling (typically 404).

## Metadata resolution order

Route metadata is derived in this order, with later sources overriding earlier
ones:

1. Route action attributes (`api_version`, `deprecated`, `sunset`, `link`)
2. Middleware parameters from `content-accord.version`
3. Attributes on controllers/methods
4. Config metadata for the version (deprecation settings)
5. Attribute metadata (wins over all other sources)

This ensures attribute-driven metadata always has the final say.
