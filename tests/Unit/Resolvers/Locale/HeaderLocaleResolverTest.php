<?php

use GaiaTools\ContentAccord\Resolvers\Locale\HeaderLocaleResolver;
use Illuminate\Http\Request;

test('resolves locale from default header', function () {
    $resolver = new HeaderLocaleResolver;
    $request = Request::create('/test');
    $request->headers->set('X-Locale', 'fr');

    expect($resolver->resolve($request))->toBe('fr');
});

test('resolves locale from custom header name', function () {
    $resolver = new HeaderLocaleResolver('App-Locale');
    $request = Request::create('/test');
    $request->headers->set('App-Locale', 'de');

    expect($resolver->resolve($request))->toBe('de');
});

test('returns null when header is absent', function () {
    $resolver = new HeaderLocaleResolver;
    $request = Request::create('/test');

    expect($resolver->resolve($request))->toBeNull();
});

test('returns null when header is empty string', function () {
    $resolver = new HeaderLocaleResolver;
    $request = Request::create('/test');
    $request->headers->set('X-Locale', '');

    expect($resolver->resolve($request))->toBeNull();
});

test('trims whitespace from header value', function () {
    $resolver = new HeaderLocaleResolver;
    $request = Request::create('/test');
    $request->headers->set('X-Locale', '  fr  ');

    expect($resolver->resolve($request))->toBe('fr');
});
