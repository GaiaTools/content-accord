<?php

use GaiaTools\ContentAccord\Http\NegotiatedContext;
use GaiaTools\ContentAccord\ValueObjects\ApiVersion;

test('can set and get values', function () {
    $context = new NegotiatedContext;
    $version = new ApiVersion(2, 1);

    $context->set('version', $version);

    expect($context->get('version'))->toBe($version);
});

test('returns null for non-existent key', function () {
    $context = new NegotiatedContext;

    expect($context->get('nonexistent'))->toBeNull();
});

test('can check if key exists', function () {
    $context = new NegotiatedContext;

    $context->set('version', new ApiVersion(1));

    expect($context->has('version'))->toBeTrue()
        ->and($context->has('nonexistent'))->toBeFalse();
});

test('can get all resolved values', function () {
    $context = new NegotiatedContext;
    $version = new ApiVersion(2, 1);

    $context->set('version', $version);
    $context->set('locale', 'en');
    $context->set('format', 'json');

    $all = $context->all();

    expect($all)->toBeArray()
        ->and($all)->toHaveCount(3)
        ->and($all['version'])->toBe($version)
        ->and($all['locale'])->toBe('en')
        ->and($all['format'])->toBe('json');
});

test('can overwrite existing values', function () {
    $context = new NegotiatedContext;

    $context->set('version', new ApiVersion(1));
    $context->set('version', new ApiVersion(2));

    expect($context->get('version'))->toEqual(new ApiVersion(2));
});

test('has returns true for null values', function () {
    $context = new NegotiatedContext;

    $context->set('nullable', null);

    expect($context->has('nullable'))->toBeTrue()
        ->and($context->get('nullable'))->toBeNull();
});

test('starts with empty resolved array', function () {
    $context = new NegotiatedContext;

    expect($context->all())->toBeEmpty();
});
