<?php

use GaiaTools\ContentAccord\Contracts\ContextResolver;
use GaiaTools\ContentAccord\Dimensions\LocaleDimension;
use GaiaTools\ContentAccord\Exceptions\MissingLocaleException;
use GaiaTools\ContentAccord\Exceptions\UnsupportedLocaleException;
use Illuminate\Http\Request;

test('returns locale as key', function () {
    $resolver = new class implements ContextResolver
    {
        public function resolve(Request $request): mixed
        {
            return 'en';
        }
    };

    $dimension = new LocaleDimension(
        resolver: $resolver,
        default: 'en',
        supportedLocales: ['en'],
    );

    expect($dimension->key())->toBe('locale');
});

test('returns the provided resolver', function () {
    $resolver = new class implements ContextResolver
    {
        public function resolve(Request $request): mixed
        {
            return 'en';
        }
    };

    $dimension = new LocaleDimension(
        resolver: $resolver,
        default: 'en',
        supportedLocales: ['en'],
    );

    expect($dimension->resolver())->toBe($resolver);
});

test('validates a supported locale', function () {
    $resolver = new class implements ContextResolver
    {
        public function resolve(Request $request): mixed
        {
            return 'en';
        }
    };

    $dimension = new LocaleDimension(
        resolver: $resolver,
        default: 'en',
        supportedLocales: ['en', 'fr'],
    );

    $request = Request::create('/test');

    expect($dimension->validate('fr', $request))->toBeTrue();
});

test('validation is case-insensitive', function () {
    $resolver = new class implements ContextResolver
    {
        public function resolve(Request $request): mixed
        {
            return 'en';
        }
    };

    $dimension = new LocaleDimension(
        resolver: $resolver,
        default: 'en',
        supportedLocales: ['en', 'fr-FR'],
    );

    $request = Request::create('/test');

    expect($dimension->validate('FR-fr', $request))->toBeTrue();
});

test('validation passes when supported list is empty', function () {
    $resolver = new class implements ContextResolver
    {
        public function resolve(Request $request): mixed
        {
            return 'en';
        }
    };

    $dimension = new LocaleDimension(
        resolver: $resolver,
        default: 'en',
        supportedLocales: [],
    );

    $request = Request::create('/test');

    expect($dimension->validate('any-locale', $request))->toBeTrue();
});

test('validation throws for unsupported locale', function () {
    $resolver = new class implements ContextResolver
    {
        public function resolve(Request $request): mixed
        {
            return 'en';
        }
    };

    $dimension = new LocaleDimension(
        resolver: $resolver,
        default: 'en',
        supportedLocales: ['en', 'fr'],
    );

    $request = Request::create('/test');

    $dimension->validate('de', $request);
})->throws(UnsupportedLocaleException::class);

test('validation throws for non-string value', function () {
    $resolver = new class implements ContextResolver
    {
        public function resolve(Request $request): mixed
        {
            return 'en';
        }
    };

    $dimension = new LocaleDimension(
        resolver: $resolver,
        default: 'en',
        supportedLocales: ['en'],
    );

    $request = Request::create('/test');

    $dimension->validate(null, $request);
})->throws(UnsupportedLocaleException::class);

test('fallback returns default locale', function () {
    $resolver = new class implements ContextResolver
    {
        public function resolve(Request $request): mixed
        {
            return null;
        }
    };

    $dimension = new LocaleDimension(
        resolver: $resolver,
        default: 'en',
        supportedLocales: ['en', 'fr'],
    );

    $request = Request::create('/test');

    expect($dimension->fallback($request))->toBe('en');
});

test('fallback throws when no default configured', function () {
    $resolver = new class implements ContextResolver
    {
        public function resolve(Request $request): mixed
        {
            return null;
        }
    };

    $dimension = new LocaleDimension(
        resolver: $resolver,
        default: '',
        supportedLocales: ['en', 'fr'],
    );

    $request = Request::create('/test');

    $dimension->fallback($request);
})->throws(MissingLocaleException::class);
