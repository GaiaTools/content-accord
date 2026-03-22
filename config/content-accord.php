<?php

use GaiaTools\ContentAccord\Dimensions\VersioningDimension;
use GaiaTools\ContentAccord\Resolvers\Version\UriVersionResolver;

return [
    /*
    |--------------------------------------------------------------------------
    | Negotiation Dimensions
    |--------------------------------------------------------------------------
    |
    | List the dimension services that should be negotiated for each request.
    | Each entry should be a container binding or class that implements
    | GaiaTools\ContentAccord\Contracts\NegotiationDimension.
    |
    */
    'dimensions' => [
        VersioningDimension::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | API Versioning Configuration
    |--------------------------------------------------------------------------
    |
    | All settings related to the API versioning dimension are nested here.
    | Future dimensions (locale, format, tenant) will be added as sibling
    | keys at the root level.
    |
    */
    'versioning' => [
        /*
        |----------------------------------------------------------------------
        | Resolver Configuration
        |----------------------------------------------------------------------
        |
        | Provide a custom resolver class or container binding, or an array
        | of resolvers to be tried in order (first non-null wins).
        | Available resolvers:
        | - GaiaTools\ContentAccord\Resolvers\Version\UriVersionResolver
        | - GaiaTools\ContentAccord\Resolvers\Version\HeaderVersionResolver
        | - GaiaTools\ContentAccord\Resolvers\Version\AcceptHeaderVersionResolver
        | - GaiaTools\ContentAccord\Resolvers\Version\QueryStringVersionResolver
        |
        */
        'resolver' => [
            UriVersionResolver::class,
            // GaiaTools\ContentAccord\Resolvers\Version\HeaderVersionResolver::class,
            // GaiaTools\ContentAccord\Resolvers\Version\AcceptHeaderVersionResolver::class,
        ],

        /*
        |----------------------------------------------------------------------
        | Missing Version Behavior
        |----------------------------------------------------------------------
        |
        | What to do when a request has no version specified.
        | Supported: "reject", "default", "latest", "require"
        |
        */
        'missing_strategy' => env('CONTENT_ACCORD_VERSIONING_MISSING', 'reject'),

        /*
        |----------------------------------------------------------------------
        | Default Version
        |----------------------------------------------------------------------
        |
        | Used when missing_strategy is set to "default".
        |
        */
        'default_version' => env('CONTENT_ACCORD_VERSIONING_DEFAULT', '1'),

        /*
        |----------------------------------------------------------------------
        | Version Fallback
        |----------------------------------------------------------------------
        |
        | When true, a request for v3 of an endpoint with only a v2 handler
        | will fall back to v2. When false, returns 404.
        | Global default — overridable per route group.
        |
        */
        'fallback' => env('CONTENT_ACCORD_VERSIONING_FALLBACK', false),

        /*
        |----------------------------------------------------------------------
        | Resolution Strategy Configurations
        |----------------------------------------------------------------------
        */
        'strategies' => [
            /*
            | URI Strategy: Extract version from route parameter
            | Example: /api/v1/users → version "1"
            */
            'uri' => [
                'prefix' => env('CONTENT_ACCORD_URI_PREFIX', 'v'),
                'parameter' => env('CONTENT_ACCORD_URI_PARAMETER', 'version'),
            ],

            /*
            | Header Strategy: Read version from custom request header
            | Example: Api-Version: 1
            */
            'header' => [
                'name' => env('CONTENT_ACCORD_HEADER_NAME', 'Api-Version'),
            ],

            /*
            | Accept Header Strategy: Parse vendor media type
            | Example: Accept: application/vnd.myapp.v1+json
            */
            'accept' => [
                'vendor' => env('CONTENT_ACCORD_ACCEPT_VENDOR', 'myapp'),
            ],

            /*
            | Query String Strategy: Read version from a query parameter
            | Example: /api/users?version=2
            */
            'query' => [
                'parameter' => env('CONTENT_ACCORD_QUERY_PARAMETER', 'version'),
            ],
        ],

        /*
        |----------------------------------------------------------------------
        | Registered Versions
        |----------------------------------------------------------------------
        |
        | Define all API versions supported by your application.
        | Each version can have deprecation metadata.
        |
        */
        'versions' => [
            '1' => [
                'deprecated' => false,
                'sunset' => null,
                'deprecation_link' => null,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Future Dimensions
    |--------------------------------------------------------------------------
    |
    | Additional negotiation dimensions can be added here as sibling keys
    | and referenced in the "dimensions" array above.
    | Examples:
    |
    | 'locale' => [
    |     'strategy' => 'header',
    |     'default' => 'en',
    |     'supported' => ['en', 'fr', 'es'],
    | ],
    |
    | 'format' => [
    |     'strategy' => 'accept',
    |     'default' => 'json',
    |     'supported' => ['json', 'xml'],
    | ],
    |
    */
];
