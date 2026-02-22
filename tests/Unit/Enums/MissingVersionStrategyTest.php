<?php

use GaiaTools\ContentAccord\Enums\MissingVersionStrategy;

test('has reject case', function () {
    expect(MissingVersionStrategy::Reject)->toBeInstanceOf(MissingVersionStrategy::class)
        ->and(MissingVersionStrategy::Reject->value)->toBe('reject');
});

test('has default version case', function () {
    expect(MissingVersionStrategy::DefaultVersion)->toBeInstanceOf(MissingVersionStrategy::class)
        ->and(MissingVersionStrategy::DefaultVersion->value)->toBe('default');
});

test('has latest version case', function () {
    expect(MissingVersionStrategy::LatestVersion)->toBeInstanceOf(MissingVersionStrategy::class)
        ->and(MissingVersionStrategy::LatestVersion->value)->toBe('latest');
});

test('has require case', function () {
    expect(MissingVersionStrategy::Require)->toBeInstanceOf(MissingVersionStrategy::class)
        ->and(MissingVersionStrategy::Require->value)->toBe('require');
});

test('can get all cases', function () {
    $cases = MissingVersionStrategy::cases();

    expect($cases)->toHaveCount(4)
        ->and($cases)->toContain(
            MissingVersionStrategy::Reject,
            MissingVersionStrategy::DefaultVersion,
            MissingVersionStrategy::LatestVersion,
            MissingVersionStrategy::Require,
        );
});

test('can be created from value', function () {
    expect(MissingVersionStrategy::from('reject'))->toBe(MissingVersionStrategy::Reject)
        ->and(MissingVersionStrategy::from('default'))->toBe(MissingVersionStrategy::DefaultVersion)
        ->and(MissingVersionStrategy::from('latest'))->toBe(MissingVersionStrategy::LatestVersion)
        ->and(MissingVersionStrategy::from('require'))->toBe(MissingVersionStrategy::Require);
});

test('throws exception for invalid value', function () {
    MissingVersionStrategy::from('invalid');
})->throws(ValueError::class);
