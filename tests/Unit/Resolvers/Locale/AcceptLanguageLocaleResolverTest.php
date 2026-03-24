<?php

use GaiaTools\ContentAccord\Resolvers\Locale\AcceptLanguageLocaleResolver;
use Illuminate\Http\Request;

test('resolves locale from simple Accept-Language header', function () {
    $resolver = new AcceptLanguageLocaleResolver;
    $request = Request::create('/test');
    $request->headers->set('Accept-Language', 'fr');

    expect($resolver->resolve($request))->toBe('fr');
});

test('resolves locale from regional Accept-Language tag', function () {
    $resolver = new AcceptLanguageLocaleResolver;
    $request = Request::create('/test');
    $request->headers->set('Accept-Language', 'fr-FR');

    expect($resolver->resolve($request))->toBe('fr-FR');
});

test('resolves first tag from weighted list', function () {
    $resolver = new AcceptLanguageLocaleResolver;
    $request = Request::create('/test');
    $request->headers->set('Accept-Language', 'fr-FR,fr;q=0.9,en-US;q=0.8,en;q=0.7');

    expect($resolver->resolve($request))->toBe('fr-FR');
});

test('resolves locale ignoring wildcard entries', function () {
    $resolver = new AcceptLanguageLocaleResolver;
    $request = Request::create('/test');
    $request->headers->set('Accept-Language', '*,en;q=0.8');

    expect($resolver->resolve($request))->toBe('en');
});

test('returns null when Accept-Language header is absent', function () {
    $resolver = new AcceptLanguageLocaleResolver;
    $request = Request::create('/test');
    $request->headers->remove('Accept-Language');

    expect($resolver->resolve($request))->toBeNull();
});

test('returns null when Accept-Language header is empty', function () {
    $resolver = new AcceptLanguageLocaleResolver;
    $request = Request::create('/test');
    $request->headers->set('Accept-Language', '');

    expect($resolver->resolve($request))->toBeNull();
});

test('returns null when only wildcard present in Accept-Language', function () {
    $resolver = new AcceptLanguageLocaleResolver;
    $request = Request::create('/test');
    $request->headers->set('Accept-Language', '*');

    expect($resolver->resolve($request))->toBeNull();
});
