<?php

use GaiaTools\ContentAccord\Contracts\ContextResolver;
use GaiaTools\ContentAccord\Resolvers\ChainedResolver;
use Illuminate\Http\Request;

test('returns first non-null result', function () {
    $resolver1 = new class implements ContextResolver
    {
        public function resolve(Request $request): mixed
        {
            return null;
        }
    };

    $resolver2 = new class implements ContextResolver
    {
        public function resolve(Request $request): mixed
        {
            return 'second';
        }
    };

    $resolver3 = new class implements ContextResolver
    {
        public function resolve(Request $request): mixed
        {
            return 'third';
        }
    };

    $chain = new ChainedResolver([$resolver1, $resolver2, $resolver3]);
    $request = Request::create('/test');

    expect($chain->resolve($request))->toBe('second');
});

test('returns null when all resolvers return null', function () {
    $resolver1 = new class implements ContextResolver
    {
        public function resolve(Request $request): mixed
        {
            return null;
        }
    };

    $resolver2 = new class implements ContextResolver
    {
        public function resolve(Request $request): mixed
        {
            return null;
        }
    };

    $chain = new ChainedResolver([$resolver1, $resolver2]);
    $request = Request::create('/test');

    expect($chain->resolve($request))->toBeNull();
});

test('returns null with empty resolvers array', function () {
    $chain = new ChainedResolver([]);
    $request = Request::create('/test');

    expect($chain->resolve($request))->toBeNull();
});

test('stops at first successful resolver', function () {
    $callCount = 0;

    $resolver1 = new class implements ContextResolver
    {
        public function resolve(Request $request): mixed
        {
            return 'first';
        }
    };

    $resolver2 = new class($callCount) implements ContextResolver
    {
        public function __construct(private int &$callCount) {}

        public function resolve(Request $request): mixed
        {
            $this->callCount++;

            return 'second';
        }
    };

    $chain = new ChainedResolver([$resolver1, $resolver2]);
    $request = Request::create('/test');

    $result = $chain->resolve($request);

    expect($result)->toBe('first')
        ->and($callCount)->toBe(0); // resolver2 should not have been called
});

test('can return various types of values', function () {
    $resolver = new class implements ContextResolver
    {
        public function resolve(Request $request): mixed
        {
            return ['key' => 'value'];
        }
    };

    $chain = new ChainedResolver([$resolver]);
    $request = Request::create('/test');

    expect($chain->resolve($request))->toBe(['key' => 'value']);
});

test('passes request to each resolver', function () {
    $receivedRequest = null;

    $resolver = new class($receivedRequest) implements ContextResolver
    {
        public function __construct(private ?Request &$receivedRequest) {}

        public function resolve(Request $request): mixed
        {
            $this->receivedRequest = $request;

            return 'value';
        }
    };

    $chain = new ChainedResolver([$resolver]);
    $request = Request::create('/test');

    $chain->resolve($request);

    expect($receivedRequest)->toBe($request);
});
