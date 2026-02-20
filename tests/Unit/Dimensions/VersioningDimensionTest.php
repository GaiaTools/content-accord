<?php

use GaiaTools\ContentAccord\Contracts\ContextResolver;
use GaiaTools\ContentAccord\Dimensions\VersioningDimension;
use GaiaTools\ContentAccord\Enums\MissingVersionStrategy;
use GaiaTools\ContentAccord\Exceptions\MissingVersionException;
use GaiaTools\ContentAccord\Exceptions\UnsupportedVersionException;
use GaiaTools\ContentAccord\ValueObjects\ApiVersion;
use Illuminate\Http\Request;

test('returns version as key', function () {
    $resolver = new class implements ContextResolver
    {
        public function resolve(Request $request): mixed
        {
            return new ApiVersion(1);
        }
    };

    $dimension = new VersioningDimension(
        resolver: $resolver,
        missingStrategy: MissingVersionStrategy::Reject,
        defaultVersion: null,
        supportedVersions: [1]
    );

    expect($dimension->key())->toBe('version');
});

test('returns the provided resolver', function () {
    $resolver = new class implements ContextResolver
    {
        public function resolve(Request $request): mixed
        {
            return new ApiVersion(1);
        }
    };

    $dimension = new VersioningDimension(
        resolver: $resolver,
        missingStrategy: MissingVersionStrategy::Reject,
        defaultVersion: null,
        supportedVersions: [1]
    );

    expect($dimension->resolver())->toBe($resolver);
});

test('validates that resolved value is an ApiVersion instance', function () {
    $resolver = new class implements ContextResolver
    {
        public function resolve(Request $request): mixed
        {
            return new ApiVersion(1);
        }
    };

    $dimension = new VersioningDimension(
        resolver: $resolver,
        missingStrategy: MissingVersionStrategy::Reject,
        defaultVersion: null,
        supportedVersions: [1]
    );

    $version = new ApiVersion(1);
    $request = Request::create('/test');

    expect($dimension->validate($version, $request))->toBeTrue();
});

test('validation fails for non-ApiVersion value', function () {
    $resolver = new class implements ContextResolver
    {
        public function resolve(Request $request): mixed
        {
            return new ApiVersion(1);
        }
    };

    $dimension = new VersioningDimension(
        resolver: $resolver,
        missingStrategy: MissingVersionStrategy::Reject,
        defaultVersion: null,
        supportedVersions: [1]
    );

    $request = Request::create('/test');

    $dimension->validate('not-a-version', $request);
})->throws(UnsupportedVersionException::class);

test('validation fails for unsupported major version', function () {
    $resolver = new class implements ContextResolver
    {
        public function resolve(Request $request): mixed
        {
            return new ApiVersion(1);
        }
    };

    $dimension = new VersioningDimension(
        resolver: $resolver,
        missingStrategy: MissingVersionStrategy::Reject,
        defaultVersion: null,
        supportedVersions: [1, 2]
    );

    $version = new ApiVersion(3); // Not in supported versions
    $request = Request::create('/test');

    $dimension->validate($version, $request);
})->throws(UnsupportedVersionException::class);

test('validation succeeds for supported major version', function () {
    $resolver = new class implements ContextResolver
    {
        public function resolve(Request $request): mixed
        {
            return new ApiVersion(1);
        }
    };

    $dimension = new VersioningDimension(
        resolver: $resolver,
        missingStrategy: MissingVersionStrategy::Reject,
        defaultVersion: null,
        supportedVersions: [1, 2, 3]
    );

    $version = new ApiVersion(2);
    $request = Request::create('/test');

    expect($dimension->validate($version, $request))->toBeTrue();
});

test('validation ignores minor version when checking support', function () {
    $resolver = new class implements ContextResolver
    {
        public function resolve(Request $request): mixed
        {
            return new ApiVersion(1);
        }
    };

    $dimension = new VersioningDimension(
        resolver: $resolver,
        missingStrategy: MissingVersionStrategy::Reject,
        defaultVersion: null,
        supportedVersions: [2]
    );

    $version = new ApiVersion(2, 5); // major=2 is supported, minor doesn't matter
    $request = Request::create('/test');

    expect($dimension->validate($version, $request))->toBeTrue();
});

test('fallback throws exception with reject strategy', function () {
    $resolver = new class implements ContextResolver
    {
        public function resolve(Request $request): mixed
        {
            return null;
        }
    };

    $dimension = new VersioningDimension(
        resolver: $resolver,
        missingStrategy: MissingVersionStrategy::Reject,
        defaultVersion: null,
        supportedVersions: [1]
    );

    $request = Request::create('/test');

    $dimension->fallback($request);
})->throws(MissingVersionException::class);

test('fallback returns default version with default strategy', function () {
    $resolver = new class implements ContextResolver
    {
        public function resolve(Request $request): mixed
        {
            return null;
        }
    };

    $defaultVersion = new ApiVersion(1);

    $dimension = new VersioningDimension(
        resolver: $resolver,
        missingStrategy: MissingVersionStrategy::DefaultVersion,
        defaultVersion: $defaultVersion,
        supportedVersions: [1]
    );

    $request = Request::create('/test');

    expect($dimension->fallback($request))->toBe($defaultVersion);
});

test('fallback throws exception with default strategy when no default version configured', function () {
    $resolver = new class implements ContextResolver
    {
        public function resolve(Request $request): mixed
        {
            return null;
        }
    };

    $dimension = new VersioningDimension(
        resolver: $resolver,
        missingStrategy: MissingVersionStrategy::DefaultVersion,
        defaultVersion: null,
        supportedVersions: [1]
    );

    $request = Request::create('/test');

    $dimension->fallback($request);
})->throws(MissingVersionException::class, 'No default version configured');

test('fallback returns latest version with latest strategy', function () {
    $resolver = new class implements ContextResolver
    {
        public function resolve(Request $request): mixed
        {
            return null;
        }
    };

    $dimension = new VersioningDimension(
        resolver: $resolver,
        missingStrategy: MissingVersionStrategy::LatestVersion,
        defaultVersion: null,
        supportedVersions: [1, 2, 3]
    );

    $request = Request::create('/test');

    $latest = $dimension->fallback($request);

    expect($latest)->toBeInstanceOf(ApiVersion::class)
        ->and($latest->major)->toBe(3);
});

test('fallback throws exception with require strategy', function () {
    $resolver = new class implements ContextResolver
    {
        public function resolve(Request $request): mixed
        {
            return null;
        }
    };

    $dimension = new VersioningDimension(
        resolver: $resolver,
        missingStrategy: MissingVersionStrategy::Require,
        defaultVersion: null,
        supportedVersions: [1, 2]
    );

    $request = Request::create('/test');

    $dimension->fallback($request);
})->throws(MissingVersionException::class);

test('fallback with require strategy includes supported versions in message', function () {
    $resolver = new class implements ContextResolver
    {
        public function resolve(Request $request): mixed
        {
            return null;
        }
    };

    $dimension = new VersioningDimension(
        resolver: $resolver,
        missingStrategy: MissingVersionStrategy::Require,
        defaultVersion: null,
        supportedVersions: [1, 2, 3]
    );

    $request = Request::create('/test');

    try {
        $dimension->fallback($request);
        $this->fail('Expected MissingVersionException to be thrown');
    } catch (MissingVersionException $e) {
        expect($e->getMessage())->toContain('v1')
            ->and($e->getMessage())->toContain('v2')
            ->and($e->getMessage())->toContain('v3');
    }
});
