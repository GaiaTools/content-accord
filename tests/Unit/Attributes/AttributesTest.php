<?php

use GaiaTools\ContentAccord\Attributes\ApiVersion;
use GaiaTools\ContentAccord\Attributes\MapToVersion;

test('api version attribute stores version', function () {
    $attribute = new ApiVersion('1.2');

    expect($attribute->version)->toBe('1.2');
});

test('map to version attribute stores version', function () {
    $attribute = new MapToVersion('2.0');

    expect($attribute->version)->toBe('2.0');
});
