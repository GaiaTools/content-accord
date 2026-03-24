<?php

use GaiaTools\ContentAccord\Exceptions\MissingLocaleException;
use GaiaTools\ContentAccord\Exceptions\UnsupportedLocaleException;

test('UnsupportedLocaleException renders a 406 response', function () {
    $exception = UnsupportedLocaleException::forLocale('de', ['en', 'fr']);

    $response = $exception->render();

    expect($response->getStatusCode())->toBe(406)
        ->and($response->getData(true)['message'])->toContain('Unsupported locale');
});

test('MissingLocaleException renders a 406 response', function () {
    $exception = new MissingLocaleException;

    $response = $exception->render();

    expect($response->getStatusCode())->toBe(406)
        ->and($response->getData(true)['message'])->toBe('Locale is required but was not provided');
});
