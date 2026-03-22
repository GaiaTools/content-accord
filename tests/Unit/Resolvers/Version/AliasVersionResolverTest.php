<?php

use GaiaTools\ContentAccord\Resolvers\Version\AliasVersionResolver;
use GaiaTools\ContentAccord\Resolvers\Version\HeaderVersionResolver;
use GaiaTools\ContentAccord\Resolvers\Version\QueryStringVersionResolver;
use GaiaTools\ContentAccord\ValueObjects\ApiVersion;
use Illuminate\Http\Request;

test('resolves alias to mapped version', function () {
    $inner = new HeaderVersionResolver('Api-Version');
    $resolver = new AliasVersionResolver(
        $inner,
        ['latest' => '3', 'stable' => '2'],
        static fn (Request $request): ?string => $request->header('Api-Version')
    );

    $request = Request::create('/api/users');
    $request->headers->set('Api-Version', 'latest');

    $version = $resolver->resolve($request);

    expect($version)->toBeInstanceOf(ApiVersion::class)
        ->and($version->major)->toBe(3);
});

test('resolves another alias from the same map', function () {
    $inner = new HeaderVersionResolver('Api-Version');
    $resolver = new AliasVersionResolver(
        $inner,
        ['latest' => '3', 'stable' => '2'],
        static fn (Request $request): ?string => $request->header('Api-Version')
    );

    $request = Request::create('/api/users');
    $request->headers->set('Api-Version', 'stable');

    $version = $resolver->resolve($request);

    expect($version)->toBeInstanceOf(ApiVersion::class)
        ->and($version->major)->toBe(2);
});

test('delegates to inner resolver when value is not an alias', function () {
    $inner = new HeaderVersionResolver('Api-Version');
    $resolver = new AliasVersionResolver(
        $inner,
        ['latest' => '3'],
        static fn (Request $request): ?string => $request->header('Api-Version')
    );

    $request = Request::create('/api/users');
    $request->headers->set('Api-Version', '1');

    $version = $resolver->resolve($request);

    expect($version)->toBeInstanceOf(ApiVersion::class)
        ->and($version->major)->toBe(1);
});

test('returns null when extractor returns null and inner returns null', function () {
    $inner = new HeaderVersionResolver('Api-Version');
    $resolver = new AliasVersionResolver(
        $inner,
        ['latest' => '3'],
        static fn (Request $request): ?string => null
    );

    $request = Request::create('/api/users');

    expect($resolver->resolve($request))->toBeNull();
});

test('falls through to inner resolver when alias target is malformed', function () {
    $inner = new QueryStringVersionResolver('version');
    $resolver = new AliasVersionResolver(
        $inner,
        ['bad-alias' => 'not-a-version'],
        static fn (Request $request): ?string => $request->query('version')
    );

    $request = Request::create('/api/users?version=bad-alias');

    // 'bad-alias' maps to 'not-a-version' which cannot be parsed,
    // so falls through to inner resolver which also returns null
    expect($resolver->resolve($request))->toBeNull();
});

test('alias is case-sensitive', function () {
    $inner = new HeaderVersionResolver('Api-Version');
    $resolver = new AliasVersionResolver(
        $inner,
        ['latest' => '3'],
        static fn (Request $request): ?string => $request->header('Api-Version')
    );

    $request = Request::create('/api/users');
    $request->headers->set('Api-Version', 'LATEST');

    // 'LATEST' != 'latest' — delegates to inner, which can't parse 'LATEST'
    expect($resolver->resolve($request))->toBeNull();
});

test('works with query string resolver', function () {
    $inner = new QueryStringVersionResolver('version');
    $resolver = new AliasVersionResolver(
        $inner,
        ['latest' => '4'],
        static fn (Request $request): ?string => $request->query('version')
    );

    $request = Request::create('/api/users?version=latest');

    $version = $resolver->resolve($request);

    expect($version)->toBeInstanceOf(ApiVersion::class)
        ->and($version->major)->toBe(4);
});
