<?php

use GaiaTools\ContentAccord\Exceptions\MissingVersionException;
use GaiaTools\ContentAccord\Exceptions\UnsupportedVersionException;
use GaiaTools\ContentAccord\ValueObjects\ApiVersion;

test('UnsupportedVersionException renders a 406 response', function () {
    $exception = UnsupportedVersionException::forVersion(new ApiVersion(99), [1, 2]);

    $response = $exception->render();

    expect($response->getStatusCode())->toBe(406)
        ->and($response->getData(true)['message'])->toContain('Unsupported API version');
});

test('MissingVersionException renders a 406 response', function () {
    $exception = new MissingVersionException;

    $response = $exception->render();

    expect($response->getStatusCode())->toBe(406)
        ->and($response->getData(true)['message'])->toBe('API version is required but was not provided');
});

test('MissingVersionException with custom message renders a 406 response', function () {
    $exception = new MissingVersionException('API version is required. Supported versions: v1, v2');

    $response = $exception->render();

    expect($response->getStatusCode())->toBe(406)
        ->and($response->getData(true)['message'])->toContain('Supported versions');
});
