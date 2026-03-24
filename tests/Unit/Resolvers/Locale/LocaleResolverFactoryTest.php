<?php

use GaiaTools\ContentAccord\Contracts\ContextResolver;
use GaiaTools\ContentAccord\Resolvers\ChainedResolver;
use GaiaTools\ContentAccord\Resolvers\Locale\AcceptLanguageLocaleResolver;
use GaiaTools\ContentAccord\Resolvers\Locale\HeaderLocaleResolver;
use GaiaTools\ContentAccord\Resolvers\Locale\LocaleResolverFactory;
use GaiaTools\ContentAccord\Resolvers\Locale\QueryStringLocaleResolver;
use Illuminate\Http\Request;

test('build throws when resolver config is null', function () {
    $factory = new LocaleResolverFactory(app(), []);

    expect(fn () => $factory->build())->toThrow(InvalidArgumentException::class);
});

test('build throws when resolver config is empty string', function () {
    $factory = new LocaleResolverFactory(app(), ['resolver' => '']);

    expect(fn () => $factory->build())->toThrow(InvalidArgumentException::class);
});

test('build returns single AcceptLanguageLocaleResolver from string config', function () {
    $factory = new LocaleResolverFactory(app(), [
        'resolver' => AcceptLanguageLocaleResolver::class,
    ]);

    expect($factory->build())->toBeInstanceOf(AcceptLanguageLocaleResolver::class);
});

test('build returns single HeaderLocaleResolver from string config', function () {
    $factory = new LocaleResolverFactory(app(), [
        'resolver' => HeaderLocaleResolver::class,
        'strategies' => ['header' => ['name' => 'X-Lang']],
    ]);

    expect($factory->build())->toBeInstanceOf(HeaderLocaleResolver::class);
});

test('build returns single QueryStringLocaleResolver from string config', function () {
    $factory = new LocaleResolverFactory(app(), [
        'resolver' => QueryStringLocaleResolver::class,
        'strategies' => ['query' => ['parameter' => 'lang']],
    ]);

    expect($factory->build())->toBeInstanceOf(QueryStringLocaleResolver::class);
});

test('build returns ChainedResolver from array config', function () {
    $factory = new LocaleResolverFactory(app(), [
        'resolver' => [
            AcceptLanguageLocaleResolver::class,
            HeaderLocaleResolver::class,
        ],
    ]);

    $resolver = $factory->build();

    expect($resolver)->toBeInstanceOf(ChainedResolver::class);

    $property = new ReflectionProperty($resolver, 'resolvers');
    $property->setAccessible(true);
    $resolvers = $property->getValue($resolver);

    expect($resolvers)->toHaveCount(2)
        ->and($resolvers[0])->toBeInstanceOf(AcceptLanguageLocaleResolver::class)
        ->and($resolvers[1])->toBeInstanceOf(HeaderLocaleResolver::class);
});

test('build uses custom resolver from container', function () {
    $factory = new LocaleResolverFactory(app(), [
        'resolver' => CustomLocaleResolver::class,
    ]);

    expect($factory->build())->toBeInstanceOf(CustomLocaleResolver::class);
});

test('build throws when custom resolver does not implement ContextResolver', function () {
    $factory = new LocaleResolverFactory(app(), [
        'resolver' => stdClass::class,
    ]);

    expect(fn () => $factory->build())->toThrow(InvalidArgumentException::class);
});

test('build throws when resolver is invalid type in array', function () {
    $factory = new LocaleResolverFactory(app(), [
        'resolver' => [42],
    ]);

    expect(fn () => $factory->build())->toThrow(InvalidArgumentException::class);
});

test('build accepts ContextResolver instance directly in array', function () {
    $instance = new AcceptLanguageLocaleResolver;

    $factory = new LocaleResolverFactory(app(), [
        'resolver' => [$instance],
    ]);

    $resolver = $factory->build();

    expect($resolver)->toBeInstanceOf(ChainedResolver::class);

    $property = new ReflectionProperty($resolver, 'resolvers');
    $property->setAccessible(true);

    expect($property->getValue($resolver)[0])->toBe($instance);
});

test('build uses default header name when config value is empty string', function () {
    $factory = new LocaleResolverFactory(app(), [
        'resolver' => HeaderLocaleResolver::class,
        'strategies' => ['header' => ['name' => '']],
    ]);

    $resolver = $factory->build();

    expect($resolver)->toBeInstanceOf(HeaderLocaleResolver::class);

    $request = Request::create('/test');
    $request->headers->set('X-Locale', 'fr');

    expect($resolver->resolve($request))->toBe('fr');
});

test('build uses default query parameter when config value is empty string', function () {
    $factory = new LocaleResolverFactory(app(), [
        'resolver' => QueryStringLocaleResolver::class,
        'strategies' => ['query' => ['parameter' => '']],
    ]);

    $resolver = $factory->build();

    expect($resolver)->toBeInstanceOf(QueryStringLocaleResolver::class);

    $request = Request::create('/test?locale=fr');

    expect($resolver->resolve($request))->toBe('fr');
});

test('build uses defaults when strategy configs are not arrays', function () {
    $factory = new LocaleResolverFactory(app(), [
        'resolver' => [
            HeaderLocaleResolver::class,
            QueryStringLocaleResolver::class,
        ],
        'strategies' => 'not-an-array',
    ]);

    $resolver = $factory->build();

    expect($resolver)->toBeInstanceOf(ChainedResolver::class);
});

class CustomLocaleResolver implements ContextResolver
{
    public function resolve(Request $request): mixed
    {
        return null;
    }
}
