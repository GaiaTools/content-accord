<?php

use GaiaTools\ContentAccord\Resolvers\Version\AcceptHeaderVersionResolver;
use GaiaTools\ContentAccord\ValueObjects\ApiVersion;
use Illuminate\Http\Request;
use Mockery as Mockery;

afterEach(function () {
    Mockery::close();
});

test('extracts version from vendor media type format', function () {
    $request = Request::create('/api/users');
    $request->headers->set('Accept', 'application/vnd.myapp.v1+json');

    $resolver = new AcceptHeaderVersionResolver('myapp');
    $version = $resolver->resolve($request);

    expect($version)->toBeInstanceOf(ApiVersion::class)
        ->and($version->major)->toBe(1)
        ->and($version->minor)->toBe(0);
});

test('extracts version with minor from vendor media type format', function () {
    $request = Request::create('/api/users');
    $request->headers->set('Accept', 'application/vnd.myapp.v2.5+json');

    $resolver = new AcceptHeaderVersionResolver('myapp');
    $version = $resolver->resolve($request);

    expect($version)->toBeInstanceOf(ApiVersion::class)
        ->and($version->major)->toBe(2)
        ->and($version->minor)->toBe(5);
});

test('extracts version from parameter format', function () {
    $request = Request::create('/api/users');
    $request->headers->set('Accept', 'application/vnd.myapp+json;version=1');

    $resolver = new AcceptHeaderVersionResolver('myapp');
    $version = $resolver->resolve($request);

    expect($version)->toBeInstanceOf(ApiVersion::class)
        ->and($version->major)->toBe(1)
        ->and($version->minor)->toBe(0);
});

test('extracts version with minor from parameter format', function () {
    $request = Request::create('/api/users');
    $request->headers->set('Accept', 'application/vnd.myapp+json;version=3.2');

    $resolver = new AcceptHeaderVersionResolver('myapp');
    $version = $resolver->resolve($request);

    expect($version)->toBeInstanceOf(ApiVersion::class)
        ->and($version->major)->toBe(3)
        ->and($version->minor)->toBe(2);
});

test('returns null when accept header is missing', function () {
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('header')->with('Accept')->andReturn(null);

    $resolver = new AcceptHeaderVersionResolver('myapp');

    expect($resolver->resolve($request))->toBeNull();
});

test('returns null when accept header is not a string', function () {
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('header')->with('Accept')->andReturn(['application/json']);

    $resolver = new AcceptHeaderVersionResolver('myapp');

    expect($resolver->resolve($request))->toBeNull();
});

test('returns null for non-vendor accept header', function () {
    $request = Request::create('/api/users');
    $request->headers->set('Accept', 'application/json');

    $resolver = new AcceptHeaderVersionResolver('myapp');

    expect($resolver->resolve($request))->toBeNull();
});

test('returns null for wrong vendor', function () {
    $request = Request::create('/api/users');
    $request->headers->set('Accept', 'application/vnd.otherapp.v1+json');

    $resolver = new AcceptHeaderVersionResolver('myapp');

    expect($resolver->resolve($request))->toBeNull();
});

test('uses custom vendor string', function () {
    $request = Request::create('/api/users');
    $request->headers->set('Accept', 'application/vnd.acme.v2+json');

    $resolver = new AcceptHeaderVersionResolver('acme');
    $version = $resolver->resolve($request);

    expect($version)->toBeInstanceOf(ApiVersion::class)
        ->and($version->major)->toBe(2);
});

test('handles multiple accept values and returns first match', function () {
    $request = Request::create('/api/users');
    $request->headers->set('Accept', 'text/html, application/vnd.myapp.v3+json, application/json');

    $resolver = new AcceptHeaderVersionResolver('myapp');
    $version = $resolver->resolve($request);

    expect($version)->toBeInstanceOf(ApiVersion::class)
        ->and($version->major)->toBe(3);
});

test('handles xml format', function () {
    $request = Request::create('/api/users');
    $request->headers->set('Accept', 'application/vnd.myapp.v1+xml');

    $resolver = new AcceptHeaderVersionResolver('myapp');
    $version = $resolver->resolve($request);

    expect($version)->toBeInstanceOf(ApiVersion::class)
        ->and($version->major)->toBe(1);
});

test('handles format without + suffix', function () {
    $request = Request::create('/api/users');
    $request->headers->set('Accept', 'application/vnd.myapp.v2');

    $resolver = new AcceptHeaderVersionResolver('myapp');
    $version = $resolver->resolve($request);

    expect($version)->toBeInstanceOf(ApiVersion::class)
        ->and($version->major)->toBe(2);
});

test('returns null for invalid version in vendor format', function () {
    $request = Request::create('/api/users');
    $request->headers->set('Accept', 'application/vnd.myapp.vinvalid+json');

    $resolver = new AcceptHeaderVersionResolver('myapp');

    expect($resolver->resolve($request))->toBeNull();
});

test('returns null for invalid version in parameter format', function () {
    $request = Request::create('/api/users');
    $request->headers->set('Accept', 'application/vnd.myapp+json;version=invalid');

    $resolver = new AcceptHeaderVersionResolver('myapp');

    expect($resolver->resolve($request))->toBeNull();
});

test('handles whitespace in accept header', function () {
    $request = Request::create('/api/users');
    $request->headers->set('Accept', ' application/vnd.myapp.v1+json ');

    $resolver = new AcceptHeaderVersionResolver('myapp');
    $version = $resolver->resolve($request);

    expect($version)->toBeInstanceOf(ApiVersion::class)
        ->and($version->major)->toBe(1);
});

test('handles whitespace in parameter format', function () {
    $request = Request::create('/api/users');
    $request->headers->set('Accept', 'application/vnd.myapp+json; version=1');

    $resolver = new AcceptHeaderVersionResolver('myapp');
    $version = $resolver->resolve($request);

    expect($version)->toBeInstanceOf(ApiVersion::class)
        ->and($version->major)->toBe(1);
});

test('parseVersion returns null for invalid version strings', function () {
    $resolver = new AcceptHeaderVersionResolver('myapp');
    $closure = Closure::bind(function (string $versionString) {
        return $this->parseVersion($versionString);
    }, $resolver, $resolver);

    expect($closure('invalid'))->toBeNull();
});
