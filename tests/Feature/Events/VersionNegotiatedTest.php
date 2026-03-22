<?php

use GaiaTools\ContentAccord\Contracts\ContextResolver;
use GaiaTools\ContentAccord\Contracts\NegotiationDimension;
use GaiaTools\ContentAccord\Dimensions\VersioningDimension;
use GaiaTools\ContentAccord\Enums\MissingVersionStrategy;
use GaiaTools\ContentAccord\Events\VersionNegotiated;
use GaiaTools\ContentAccord\Http\Middleware\NegotiateContext;
use GaiaTools\ContentAccord\Http\NegotiatedContext;
use GaiaTools\ContentAccord\Resolvers\Version\HeaderVersionResolver;
use GaiaTools\ContentAccord\ValueObjects\ApiVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

test('VersionNegotiated event is fired after version is resolved', function () {
    Event::fake([VersionNegotiated::class]);

    $context = new NegotiatedContext;
    $dimension = new VersioningDimension(
        resolver: new HeaderVersionResolver('Api-Version'),
        missingStrategy: MissingVersionStrategy::Reject,
        defaultVersion: null,
        supportedVersions: [1, 2]
    );
    $middleware = new NegotiateContext([$dimension], $context);

    $request = Request::create('/test');
    $request->headers->set('Api-Version', '2');

    $middleware->handle($request, fn ($req) => response('OK'));

    Event::assertDispatched(VersionNegotiated::class, function (VersionNegotiated $event) {
        return $event->version->major === 2;
    });
});

test('VersionNegotiated event carries the resolved ApiVersion', function () {
    Event::fake([VersionNegotiated::class]);

    $context = new NegotiatedContext;
    $dimension = new VersioningDimension(
        resolver: new HeaderVersionResolver('Api-Version'),
        missingStrategy: MissingVersionStrategy::Reject,
        defaultVersion: null,
        supportedVersions: [1]
    );
    $middleware = new NegotiateContext([$dimension], $context);

    $request = Request::create('/test');
    $request->headers->set('Api-Version', '1');

    $middleware->handle($request, fn ($req) => response('OK'));

    Event::assertDispatched(VersionNegotiated::class, function (VersionNegotiated $event) use ($request) {
        return $event->version instanceof ApiVersion
            && $event->version->major === 1
            && $event->request === $request;
    });
});

test('VersionNegotiated event is fired even when version comes from fallback', function () {
    Event::fake([VersionNegotiated::class]);

    $defaultVersion = new ApiVersion(1);
    $context = new NegotiatedContext;
    $dimension = new VersioningDimension(
        resolver: new HeaderVersionResolver('Api-Version'),
        missingStrategy: MissingVersionStrategy::DefaultVersion,
        defaultVersion: $defaultVersion,
        supportedVersions: [1]
    );
    $middleware = new NegotiateContext([$dimension], $context);

    $request = Request::create('/test');
    // No Api-Version header — version comes from fallback

    $middleware->handle($request, fn ($req) => response('OK'));

    Event::assertDispatched(VersionNegotiated::class, function (VersionNegotiated $event) {
        return $event->version->major === 1;
    });
});

test('VersionNegotiated is not fired for non-version dimensions', function () {
    Event::fake([VersionNegotiated::class]);

    $context = new NegotiatedContext;

    $localeDimension = new class implements NegotiationDimension
    {
        public function key(): string
        {
            return 'locale';
        }

        public function resolver(): ContextResolver
        {
            return new class implements ContextResolver
            {
                public function resolve(Request $request): mixed
                {
                    return 'en';
                }
            };
        }

        public function validate(mixed $resolved, Request $request): bool
        {
            return true;
        }

        public function fallback(Request $request): mixed
        {
            return 'en';
        }
    };

    $middleware = new NegotiateContext([$localeDimension], $context);

    $request = Request::create('/test');

    $middleware->handle($request, fn ($req) => response('OK'));

    Event::assertNotDispatched(VersionNegotiated::class);
});
