<?php

use GaiaTools\ContentAccord\Resolvers\Locale\QueryStringLocaleResolver;
use Illuminate\Http\Request;

test('resolves locale from default query parameter', function () {
    $resolver = new QueryStringLocaleResolver;
    $request = Request::create('/test?locale=fr');

    expect($resolver->resolve($request))->toBe('fr');
});

test('resolves locale from custom query parameter', function () {
    $resolver = new QueryStringLocaleResolver('lang');
    $request = Request::create('/test?lang=de');

    expect($resolver->resolve($request))->toBe('de');
});

test('returns null when parameter is absent', function () {
    $resolver = new QueryStringLocaleResolver;
    $request = Request::create('/test');

    expect($resolver->resolve($request))->toBeNull();
});

test('returns null when parameter is empty string', function () {
    $resolver = new QueryStringLocaleResolver;
    $request = Request::create('/test?locale=');

    expect($resolver->resolve($request))->toBeNull();
});

test('trims whitespace from parameter value', function () {
    $resolver = new QueryStringLocaleResolver;
    $request = Request::create('/test?locale=+fr+');

    expect($resolver->resolve($request))->toBe('fr');
});
