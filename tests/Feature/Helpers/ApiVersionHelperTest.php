<?php

use GaiaTools\ContentAccord\Http\NegotiatedContext;
use GaiaTools\ContentAccord\ValueObjects\ApiVersion;

test('apiVersion helper returns version from negotiated context', function () {
    $context = app(NegotiatedContext::class);
    $version = ApiVersion::parse('2');

    $context->set('version', $version);

    expect(apiVersion())->toBe($version);
});

test('apiVersion helper returns null when context resolution fails', function () {
    app()->bind(NegotiatedContext::class, function () {
        throw new RuntimeException('Context resolution failed.');
    });

    expect(apiVersion())->toBeNull();
});
