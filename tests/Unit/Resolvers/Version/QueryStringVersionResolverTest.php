<?php

use GaiaTools\ContentAccord\Resolvers\Version\QueryStringVersionResolver;
use GaiaTools\ContentAccord\ValueObjects\ApiVersion;
use Illuminate\Http\Request;

test('extracts version from query string', function () {
    $request = Request::create('/api/users?version=1');

    $resolver = new QueryStringVersionResolver('version');
    $version = $resolver->resolve($request);

    expect($version)->toBeInstanceOf(ApiVersion::class)
        ->and($version->major)->toBe(1)
        ->and($version->minor)->toBe(0);
});

test('extracts version with minor from query string', function () {
    $request = Request::create('/api/users?version=2.5');

    $resolver = new QueryStringVersionResolver('version');
    $version = $resolver->resolve($request);

    expect($version)->toBeInstanceOf(ApiVersion::class)
        ->and($version->major)->toBe(2)
        ->and($version->minor)->toBe(5);
});

test('handles v prefix in version value', function () {
    $request = Request::create('/api/users?version=v3');

    $resolver = new QueryStringVersionResolver('version');
    $version = $resolver->resolve($request);

    expect($version)->toBeInstanceOf(ApiVersion::class)
        ->and($version->major)->toBe(3);
});

test('returns null when query parameter is missing', function () {
    $request = Request::create('/api/users');

    $resolver = new QueryStringVersionResolver('version');

    expect($resolver->resolve($request))->toBeNull();
});

test('returns null for invalid version format', function () {
    $request = Request::create('/api/users?version=bad');

    $resolver = new QueryStringVersionResolver('version');

    expect($resolver->resolve($request))->toBeNull();
});

test('returns null for empty query parameter value', function () {
    $request = Request::create('/api/users?version=');

    $resolver = new QueryStringVersionResolver('version');

    expect($resolver->resolve($request))->toBeNull();
});

test('uses custom parameter name', function () {
    $request = Request::create('/api/users?v=2');

    $resolver = new QueryStringVersionResolver('v');
    $version = $resolver->resolve($request);

    expect($version)->toBeInstanceOf(ApiVersion::class)
        ->and($version->major)->toBe(2);
});

test('returns null when only a different parameter is present', function () {
    $request = Request::create('/api/users?v=1');

    $resolver = new QueryStringVersionResolver('version');

    expect($resolver->resolve($request))->toBeNull();
});
