<?php

// Namespace-shadow preg_replace inside GaiaTools\ContentAccord\ValueObjects so that
// ApiVersion::parse (which calls preg_replace without a leading \) can be made to see
// a null return value — the only way to exercise line 31 in ApiVersion.php.
// The override delegates to the real function for every input except the sentinel string,
// so all other ApiVersion tests continue to work normally.
namespace GaiaTools\ContentAccord\ValueObjects {
    function preg_replace(mixed $pattern, mixed $replacement, mixed $subject, mixed ...$args): mixed
    {
        if ($subject === '__PREG_NULL_SENTINEL__') {
            return null;
        }

        return \preg_replace($pattern, $replacement, $subject, ...$args);
    }
}

namespace {
    use GaiaTools\ContentAccord\Exceptions\InvalidVersionFormatException;
    use GaiaTools\ContentAccord\ValueObjects\ApiVersion;

    test('can be instantiated with major version only', function () {
        $version = new ApiVersion(1);

        expect($version->major)->toBe(1)
            ->and($version->minor)->toBe(0);
    });

    test('can be instantiated with major and minor versions', function () {
        $version = new ApiVersion(2, 3);

        expect($version->major)->toBe(2)
            ->and($version->minor)->toBe(3);
    });

    test('can parse simple major version', function () {
        $version = ApiVersion::parse('1');

        expect($version->major)->toBe(1)
            ->and($version->minor)->toBe(0);
    });

    test('can parse major.minor version', function () {
        $version = ApiVersion::parse('1.2');

        expect($version->major)->toBe(1)
            ->and($version->minor)->toBe(2);
    });

    test('can parse version with v prefix', function () {
        $version = ApiVersion::parse('v1');

        expect($version->major)->toBe(1)
            ->and($version->minor)->toBe(0);
    });

    test('can parse version with v prefix and minor', function () {
        $version = ApiVersion::parse('v2.5');

        expect($version->major)->toBe(2)
            ->and($version->minor)->toBe(5);
    });

    test('throws exception for invalid version format', function () {
        ApiVersion::parse('invalid');
    })->throws(InvalidVersionFormatException::class);

    test('throws exception for empty version string', function () {
        ApiVersion::parse('');
    })->throws(InvalidVersionFormatException::class);

    test('throws exception for negative version', function () {
        ApiVersion::parse('-1');
    })->throws(InvalidVersionFormatException::class);

    test('throws exception for version with letters', function () {
        ApiVersion::parse('1.2.3');
    })->throws(InvalidVersionFormatException::class);

    test('throws exception when preg_replace returns null (simulated PCRE failure)', function () {
        // The namespaced preg_replace override returns null for this sentinel string,
        // exercising line 31: if ($normalized === null) { throw ... }
        ApiVersion::parse('__PREG_NULL_SENTINEL__');
    })->throws(InvalidVersionFormatException::class);

    test('satisfies checks major version only', function () {
        $version1 = new ApiVersion(2, 0);
        $version2 = new ApiVersion(2, 5);

        expect($version1->satisfies($version2))->toBeTrue()
            ->and($version2->satisfies($version1))->toBeTrue();
    });

    test('does not satisfy different major version', function () {
        $version1 = new ApiVersion(1, 5);
        $version2 = new ApiVersion(2, 0);

        expect($version1->satisfies($version2))->toBeFalse();
    });

    test('equals checks both major and minor', function () {
        $version1 = new ApiVersion(2, 3);
        $version2 = new ApiVersion(2, 3);

        expect($version1->equals($version2))->toBeTrue();
    });

    test('not equals when minor differs', function () {
        $version1 = new ApiVersion(2, 3);
        $version2 = new ApiVersion(2, 4);

        expect($version1->equals($version2))->toBeFalse();
    });

    test('not equals when major differs', function () {
        $version1 = new ApiVersion(1, 3);
        $version2 = new ApiVersion(2, 3);

        expect($version1->equals($version2))->toBeFalse();
    });

    test('is greater than compares major first', function () {
        $version1 = new ApiVersion(2, 0);
        $version2 = new ApiVersion(1, 9);

        expect($version1->isGreaterThan($version2))->toBeTrue()
            ->and($version2->isGreaterThan($version1))->toBeFalse();
    });

    test('is greater than compares minor when major is same', function () {
        $version1 = new ApiVersion(2, 5);
        $version2 = new ApiVersion(2, 3);

        expect($version1->isGreaterThan($version2))->toBeTrue()
            ->and($version2->isGreaterThan($version1))->toBeFalse();
    });

    test('is not greater than when equal', function () {
        $version1 = new ApiVersion(2, 3);
        $version2 = new ApiVersion(2, 3);

        expect($version1->isGreaterThan($version2))->toBeFalse();
    });

    test('is less than compares major first', function () {
        $version1 = new ApiVersion(1, 9);
        $version2 = new ApiVersion(2, 0);

        expect($version1->isLessThan($version2))->toBeTrue()
            ->and($version2->isLessThan($version1))->toBeFalse();
    });

    test('is less than compares minor when major is same', function () {
        $version1 = new ApiVersion(2, 3);
        $version2 = new ApiVersion(2, 5);

        expect($version1->isLessThan($version2))->toBeTrue()
            ->and($version2->isLessThan($version1))->toBeFalse();
    });

    test('is not less than when equal', function () {
        $version1 = new ApiVersion(2, 3);
        $version2 = new ApiVersion(2, 3);

        expect($version1->isLessThan($version2))->toBeFalse();
    });

    test('toString formats as major.minor', function () {
        $version = new ApiVersion(2, 5);

        expect($version->toString())->toBe('2.5');
    });

    test('toString formats minor as 0 when not provided', function () {
        $version = new ApiVersion(1);

        expect($version->toString())->toBe('1.0');
    });

    test('can be cast to string', function () {
        $version = new ApiVersion(3, 2);

        expect((string) $version)->toBe('3.2');
    });

    test('implements stringable', function () {
        $version = new ApiVersion(1, 0);

        expect($version)->toBeInstanceOf(Stringable::class);
    });
}
