<?php

use GaiaTools\ContentAccord\Resolvers\Version\HeaderVersionResolver;
use GaiaTools\ContentAccord\ValueObjects\ApiVersion;
use Illuminate\Http\Request;

test('extracts version from header', function () {
    $request = Request::create('/api/users');
    $request->headers->set('Api-Version', '1');

    $resolver = new HeaderVersionResolver('Api-Version');
    $version = $resolver->resolve($request);

    expect($version)->toBeInstanceOf(ApiVersion::class)
        ->and($version->major)->toBe(1)
        ->and($version->minor)->toBe(0);
});

test('extracts version with minor from header', function () {
    $request = Request::create('/api/users');
    $request->headers->set('Api-Version', '2.5');

    $resolver = new HeaderVersionResolver('Api-Version');
    $version = $resolver->resolve($request);

    expect($version)->toBeInstanceOf(ApiVersion::class)
        ->and($version->major)->toBe(2)
        ->and($version->minor)->toBe(5);
});

test('returns null when header is missing', function () {
    $request = Request::create('/api/users');

    $resolver = new HeaderVersionResolver('Api-Version');

    expect($resolver->resolve($request))->toBeNull();
});

test('returns null for invalid version format', function () {
    $request = Request::create('/api/users');
    $request->headers->set('Api-Version', 'invalid');

    $resolver = new HeaderVersionResolver('Api-Version');

    expect($resolver->resolve($request))->toBeNull();
});

test('uses custom header name', function () {
    $request = Request::create('/api/users');
    $request->headers->set('X-API-Version', '3');

    $resolver = new HeaderVersionResolver('X-API-Version');
    $version = $resolver->resolve($request);

    expect($version)->toBeInstanceOf(ApiVersion::class)
        ->and($version->major)->toBe(3);
});

test('handles version with v prefix', function () {
    $request = Request::create('/api/users');
    $request->headers->set('Api-Version', 'v2.1');

    $resolver = new HeaderVersionResolver('Api-Version');
    $version = $resolver->resolve($request);

    expect($version)->toBeInstanceOf(ApiVersion::class)
        ->and($version->major)->toBe(2)
        ->and($version->minor)->toBe(1);
});

test('returns null for empty header value', function () {
    $request = Request::create('/api/users');
    $request->headers->set('Api-Version', '');

    $resolver = new HeaderVersionResolver('Api-Version');

    expect($resolver->resolve($request))->toBeNull();
});

test('header name is case insensitive', function () {
    $request = Request::create('/api/users');
    $request->headers->set('api-version', '1');

    $resolver = new HeaderVersionResolver('Api-Version');
    $version = $resolver->resolve($request);

    expect($version)->toBeInstanceOf(ApiVersion::class)
        ->and($version->major)->toBe(1);
});
