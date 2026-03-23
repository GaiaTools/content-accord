<?php

use GaiaTools\ContentAccord\Contracts\ContextResolver;
use GaiaTools\ContentAccord\Resolvers\ChainedResolver;
use GaiaTools\ContentAccord\Resolvers\Version\AcceptHeaderVersionResolver;
use GaiaTools\ContentAccord\Resolvers\Version\AliasVersionResolver;
use GaiaTools\ContentAccord\Resolvers\Version\HeaderVersionResolver;
use GaiaTools\ContentAccord\Resolvers\Version\QueryStringVersionResolver;
use GaiaTools\ContentAccord\Resolvers\Version\UriVersionResolver;
use GaiaTools\ContentAccord\Resolvers\Version\VersionResolverFactory;
use GaiaTools\ContentAccord\ValueObjects\ApiVersion;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;

test('build throws when resolver config is null', function () {
    $factory = new VersionResolverFactory(app(), []);

    expect(fn () => $factory->build())->toThrow(InvalidArgumentException::class);
});

test('build throws when resolver config is empty string', function () {
    $factory = new VersionResolverFactory(app(), ['resolver' => '']);

    expect(fn () => $factory->build())->toThrow(InvalidArgumentException::class);
});

test('build returns single UriVersionResolver from string config', function () {
    $factory = new VersionResolverFactory(app(), [
        'resolver' => UriVersionResolver::class,
        'strategies' => ['uri' => ['parameter' => 'version', 'prefix' => 'v']],
    ]);

    $resolver = $factory->build();

    expect($resolver)->toBeInstanceOf(UriVersionResolver::class);
});

test('build returns single HeaderVersionResolver from string config', function () {
    $factory = new VersionResolverFactory(app(), [
        'resolver' => HeaderVersionResolver::class,
        'strategies' => ['header' => ['name' => 'X-Api-Version']],
    ]);

    $resolver = $factory->build();

    expect($resolver)->toBeInstanceOf(HeaderVersionResolver::class);
});

test('build returns single AcceptHeaderVersionResolver from string config', function () {
    $factory = new VersionResolverFactory(app(), [
        'resolver' => AcceptHeaderVersionResolver::class,
        'strategies' => ['accept' => ['vendor' => 'myapp']],
    ]);

    $resolver = $factory->build();

    expect($resolver)->toBeInstanceOf(AcceptHeaderVersionResolver::class);
});

test('build returns single QueryStringVersionResolver from string config', function () {
    $factory = new VersionResolverFactory(app(), [
        'resolver' => QueryStringVersionResolver::class,
        'strategies' => ['query' => ['parameter' => 'version']],
    ]);

    $resolver = $factory->build();

    expect($resolver)->toBeInstanceOf(QueryStringVersionResolver::class);
});

test('build returns ChainedResolver from array config', function () {
    $factory = new VersionResolverFactory(app(), [
        'resolver' => [UriVersionResolver::class, HeaderVersionResolver::class],
        'strategies' => [
            'uri' => ['parameter' => 'version', 'prefix' => 'v'],
            'header' => ['name' => 'Api-Version'],
        ],
    ]);

    $resolver = $factory->build();

    expect($resolver)->toBeInstanceOf(ChainedResolver::class);
});

test('build uses custom resolver from container', function () {
    $customResolver = new class implements ContextResolver
    {
        public function resolve(Request $request): mixed
        {
            return new ApiVersion(1);
        }
    };
    $class = get_class($customResolver);

    app()->bind($class, fn () => $customResolver);

    $factory = new VersionResolverFactory(app(), [
        'resolver' => $class,
    ]);

    $resolver = $factory->build();

    expect($resolver)->toBeInstanceOf(ContextResolver::class);
});

test('build throws when custom resolver does not implement ContextResolver', function () {
    $notAResolver = new class
    {
        public function resolve(Request $request): mixed
        {
            return null;
        }
    };
    $class = get_class($notAResolver);

    app()->bind($class, fn () => $notAResolver);

    $factory = new VersionResolverFactory(app(), [
        'resolver' => $class,
    ]);

    expect(fn () => $factory->build())->toThrow(InvalidArgumentException::class);
});

test('build throws when resolver is invalid type (non-string in array)', function () {
    $factory = new VersionResolverFactory(app(), [
        'resolver' => [123],
    ]);

    expect(fn () => $factory->build())->toThrow(InvalidArgumentException::class);
});

test('build wraps with AliasVersionResolver when aliases configured for UriVersionResolver', function () {
    $factory = new VersionResolverFactory(app(), [
        'resolver' => UriVersionResolver::class,
        'strategies' => ['uri' => ['parameter' => 'version', 'prefix' => 'v']],
        'aliases' => ['latest' => '1'],
    ]);

    $resolver = $factory->build();

    expect($resolver)->toBeInstanceOf(AliasVersionResolver::class);
});

test('build wraps with AliasVersionResolver when aliases configured for HeaderVersionResolver', function () {
    $factory = new VersionResolverFactory(app(), [
        'resolver' => HeaderVersionResolver::class,
        'strategies' => ['header' => ['name' => 'Api-Version']],
        'aliases' => ['stable' => '2'],
    ]);

    $resolver = $factory->build();

    expect($resolver)->toBeInstanceOf(AliasVersionResolver::class);
});

test('build wraps with AliasVersionResolver when aliases configured for QueryStringVersionResolver', function () {
    $factory = new VersionResolverFactory(app(), [
        'resolver' => QueryStringVersionResolver::class,
        'strategies' => ['query' => ['parameter' => 'v']],
        'aliases' => ['current' => '3'],
    ]);

    $resolver = $factory->build();

    expect($resolver)->toBeInstanceOf(AliasVersionResolver::class);
});

test('build does not wrap AcceptHeaderVersionResolver with aliases (not applicable)', function () {
    $factory = new VersionResolverFactory(app(), [
        'resolver' => AcceptHeaderVersionResolver::class,
        'strategies' => ['accept' => ['vendor' => 'myapp']],
        'aliases' => ['latest' => '1'],
    ]);

    $resolver = $factory->build();

    // AcceptHeaderVersionResolver returns null extractor so no alias wrapping
    expect($resolver)->toBeInstanceOf(AcceptHeaderVersionResolver::class);
});

test('build does not wrap when aliases config is empty array', function () {
    $factory = new VersionResolverFactory(app(), [
        'resolver' => UriVersionResolver::class,
        'strategies' => ['uri' => ['parameter' => 'version', 'prefix' => 'v']],
        'aliases' => [],
    ]);

    $resolver = $factory->build();

    expect($resolver)->toBeInstanceOf(UriVersionResolver::class);
});

test('build does not wrap when aliases has no valid entries', function () {
    $factory = new VersionResolverFactory(app(), [
        'resolver' => UriVersionResolver::class,
        'strategies' => ['uri' => ['parameter' => 'version', 'prefix' => 'v']],
        'aliases' => [123 => 'bad'],
    ]);

    $resolver = $factory->build();

    expect($resolver)->toBeInstanceOf(UriVersionResolver::class);
});

test('build uses default values when strategy config is not an array', function () {
    $factory = new VersionResolverFactory(app(), [
        'resolver' => UriVersionResolver::class,
        'strategies' => 'not-an-array',
    ]);

    $resolver = $factory->build();

    expect($resolver)->toBeInstanceOf(UriVersionResolver::class);
});

test('build uses defaults when individual strategy configs are not arrays', function () {
    $factory = new VersionResolverFactory(app(), [
        'resolver' => HeaderVersionResolver::class,
        'strategies' => [
            'header' => 'not-an-array',
        ],
    ]);

    $resolver = $factory->build();

    expect($resolver)->toBeInstanceOf(HeaderVersionResolver::class);
});

test('build uses default uri parameter when config value is empty string', function () {
    $factory = new VersionResolverFactory(app(), [
        'resolver' => UriVersionResolver::class,
        'strategies' => ['uri' => ['parameter' => '', 'prefix' => '']],
    ]);

    // Should not throw — defaults kick in
    $resolver = $factory->build();

    expect($resolver)->toBeInstanceOf(UriVersionResolver::class);
});

test('build uses default header name when config value is empty string', function () {
    $factory = new VersionResolverFactory(app(), [
        'resolver' => HeaderVersionResolver::class,
        'strategies' => ['header' => ['name' => '']],
    ]);

    $resolver = $factory->build();

    expect($resolver)->toBeInstanceOf(HeaderVersionResolver::class);
});

test('build uses default vendor when config value is empty string', function () {
    $factory = new VersionResolverFactory(app(), [
        'resolver' => AcceptHeaderVersionResolver::class,
        'strategies' => ['accept' => ['vendor' => '']],
    ]);

    $resolver = $factory->build();

    expect($resolver)->toBeInstanceOf(AcceptHeaderVersionResolver::class);
});

test('build uses default query parameter when config value is empty string', function () {
    $factory = new VersionResolverFactory(app(), [
        'resolver' => QueryStringVersionResolver::class,
        'strategies' => ['query' => ['parameter' => '']],
    ]);

    $resolver = $factory->build();

    expect($resolver)->toBeInstanceOf(QueryStringVersionResolver::class);
});

test('alias raw extractor for URI resolver extracts route parameter', function () {
    $factory = new VersionResolverFactory(app(), [
        'resolver' => UriVersionResolver::class,
        'strategies' => ['uri' => ['parameter' => 'version', 'prefix' => 'v']],
        'aliases' => ['latest' => '1'],
    ]);

    $resolver = $factory->build();
    expect($resolver)->toBeInstanceOf(AliasVersionResolver::class);

    // Bind request first, then set the parameter
    $request = Request::create('/v1/users');
    $route = new Route('GET', '/v{version}/users', []);
    $route->bind($request);
    $route->setParameter('version', 'latest');
    $request->setRouteResolver(fn () => $route);

    $version = $resolver->resolve($request);

    expect($version)->toBeInstanceOf(ApiVersion::class)
        ->and($version->major)->toBe(1);
});

test('alias raw extractor for Header resolver extracts header value', function () {
    $factory = new VersionResolverFactory(app(), [
        'resolver' => HeaderVersionResolver::class,
        'strategies' => ['header' => ['name' => 'Api-Version']],
        'aliases' => ['stable' => '2'],
    ]);

    $resolver = $factory->build();
    expect($resolver)->toBeInstanceOf(AliasVersionResolver::class);

    $request = Request::create('/users');
    $request->headers->set('Api-Version', 'stable');

    $version = $resolver->resolve($request);

    expect($version)->toBeInstanceOf(ApiVersion::class)
        ->and($version->major)->toBe(2);
});

test('alias raw extractor for Query resolver extracts query value', function () {
    $factory = new VersionResolverFactory(app(), [
        'resolver' => QueryStringVersionResolver::class,
        'strategies' => ['query' => ['parameter' => 'v']],
        'aliases' => ['current' => '3'],
    ]);

    $resolver = $factory->build();
    expect($resolver)->toBeInstanceOf(AliasVersionResolver::class);

    $request = Request::create('/users', 'GET', ['v' => 'current']);

    $version = $resolver->resolve($request);

    expect($version)->toBeInstanceOf(ApiVersion::class)
        ->and($version->major)->toBe(3);
});

test('build accepts ContextResolver instance directly in array', function () {
    $instance = new HeaderVersionResolver('My-Header');

    $factory = new VersionResolverFactory(app(), [
        'resolver' => [$instance],
    ]);

    $resolver = $factory->build();

    expect($resolver)->toBeInstanceOf(ChainedResolver::class);
});
