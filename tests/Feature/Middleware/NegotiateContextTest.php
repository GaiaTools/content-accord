<?php

use GaiaTools\ContentAccord\Attributes\ApiNegotiate;
use GaiaTools\ContentAccord\Contracts\ContextResolver;
use GaiaTools\ContentAccord\Contracts\NegotiationDimension;
use GaiaTools\ContentAccord\Dimensions\VersioningDimension;
use GaiaTools\ContentAccord\Enums\MissingVersionStrategy;
use GaiaTools\ContentAccord\Exceptions\MissingVersionException;
use GaiaTools\ContentAccord\Exceptions\UnsupportedVersionException;
use GaiaTools\ContentAccord\Http\Middleware\NegotiateContext;
use GaiaTools\ContentAccord\Http\NegotiatedContext;
use GaiaTools\ContentAccord\Resolvers\Version\HeaderVersionResolver;
use GaiaTools\ContentAccord\ValueObjects\ApiVersion;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;

test('populates negotiated context with resolved dimension values', function () {
    $context = new NegotiatedContext();
    $resolver = new HeaderVersionResolver('Api-Version');
    $dimension = new VersioningDimension(
        resolver: $resolver,
        missingStrategy: MissingVersionStrategy::Reject,
        defaultVersion: null,
        supportedVersions: [1]
    );

    $middleware = new NegotiateContext([$dimension], $context);

    $request = Request::create('/test');
    $request->headers->set('Api-Version', '1');

    $response = $middleware->handle($request, fn ($req) => response('OK'));

    expect($context->has('version'))->toBeTrue()
        ->and($context->get('version'))->toBeInstanceOf(ApiVersion::class)
        ->and($context->get('version')->major)->toBe(1);
});

test('uses fallback when resolver returns null', function () {
    $context = new NegotiatedContext();
    $resolver = new HeaderVersionResolver('Api-Version');
    $defaultVersion = new ApiVersion(1);

    $dimension = new VersioningDimension(
        resolver: $resolver,
        missingStrategy: MissingVersionStrategy::DefaultVersion,
        defaultVersion: $defaultVersion,
        supportedVersions: [1]
    );

    $middleware = new NegotiateContext([$dimension], $context);

    $request = Request::create('/test');
    // No Api-Version header, should use fallback

    $response = $middleware->handle($request, fn ($req) => response('OK'));

    expect($context->has('version'))->toBeTrue()
        ->and($context->get('version'))->toBe($defaultVersion);
});

test('validates resolved value', function () {
    $context = new NegotiatedContext();
    $resolver = new HeaderVersionResolver('Api-Version');

    $dimension = new VersioningDimension(
        resolver: $resolver,
        missingStrategy: MissingVersionStrategy::Reject,
        defaultVersion: null,
        supportedVersions: [1, 2]
    );

    $middleware = new NegotiateContext([$dimension], $context);

    $request = Request::create('/test');
    $request->headers->set('Api-Version', '3'); // Unsupported version

    $middleware->handle($request, fn ($req) => response('OK'));
})->throws(UnsupportedVersionException::class);

test('throws exception when fallback fails', function () {
    $context = new NegotiatedContext();
    $resolver = new HeaderVersionResolver('Api-Version');

    $dimension = new VersioningDimension(
        resolver: $resolver,
        missingStrategy: MissingVersionStrategy::Reject,
        defaultVersion: null,
        supportedVersions: [1]
    );

    $middleware = new NegotiateContext([$dimension], $context);

    $request = Request::create('/test');
    // No Api-Version header and Reject strategy

    $middleware->handle($request, fn ($req) => response('OK'));
})->throws(MissingVersionException::class);

test('processes multiple dimensions', function () {
    $context = new NegotiatedContext();

    // Version dimension
    $versionResolver = new HeaderVersionResolver('Api-Version');
    $versionDimension = new VersioningDimension(
        resolver: $versionResolver,
        missingStrategy: MissingVersionStrategy::Reject,
        defaultVersion: null,
        supportedVersions: [1]
    );

    // Mock locale dimension
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
                    return $request->header('Accept-Language', 'en');
                }
            };
        }

        public function validate(mixed $resolved, Request $request): bool
        {
            return is_string($resolved);
        }

        public function fallback(Request $request): mixed
        {
            return 'en';
        }
    };

    $middleware = new NegotiateContext([$versionDimension, $localeDimension], $context);

    $request = Request::create('/test');
    $request->headers->set('Api-Version', '1');
    $request->headers->set('Accept-Language', 'fr');

    $response = $middleware->handle($request, fn ($req) => response('OK'));

    expect($context->has('version'))->toBeTrue()
        ->and($context->get('version')->major)->toBe(1)
        ->and($context->has('locale'))->toBeTrue()
        ->and($context->get('locale'))->toBe('fr');
});

test('processes dimensions with no dimensions array', function () {
    $context = new NegotiatedContext();
    $middleware = new NegotiateContext([], $context);

    $request = Request::create('/test');

    $response = $middleware->handle($request, fn ($req) => response('OK'));

    expect($context->all())->toBeEmpty();
});

test('calls next closure and returns response', function () {
    $context = new NegotiatedContext();
    $resolver = new HeaderVersionResolver('Api-Version');
    $dimension = new VersioningDimension(
        resolver: $resolver,
        missingStrategy: MissingVersionStrategy::Reject,
        defaultVersion: null,
        supportedVersions: [1]
    );

    $middleware = new NegotiateContext([$dimension], $context);

    $request = Request::create('/test');
    $request->headers->set('Api-Version', '1');

    $response = $middleware->handle($request, fn ($req) => response('Test Response', 200));

    expect($response->getContent())->toBe('Test Response')
        ->and($response->getStatusCode())->toBe(200);
});

test('skips dimensions configured on the route', function () {
    $context = new NegotiatedContext();

    $versionDimension = new class implements NegotiationDimension
    {
        public function key(): string
        {
            return 'version';
        }

        public function resolver(): ContextResolver
        {
            throw new RuntimeException('Version resolver should not be called.');
        }

        public function validate(mixed $resolved, Request $request): bool
        {
            return true;
        }

        public function fallback(Request $request): mixed
        {
            return null;
        }
    };

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

    $middleware = new NegotiateContext([$versionDimension, $localeDimension], $context);

    $request = Request::create('/test');
    $route = new Route(['GET'], '/test', []);
    $route->defaults('content_accord.skip', ['version']);
    $request->setRouteResolver(fn () => $route);

    $middleware->handle($request, fn ($req) => response('OK'));

    expect($context->has('version'))->toBeFalse()
        ->and($context->has('locale'))->toBeTrue()
        ->and($context->get('locale'))->toBe('en');
});

test('only processes dimensions configured on the route', function () {
    $context = new NegotiatedContext();

    $versionDimension = new class implements NegotiationDimension
    {
        public function key(): string
        {
            return 'version';
        }

        public function resolver(): ContextResolver
        {
            throw new RuntimeException('Version resolver should not be called.');
        }

        public function validate(mixed $resolved, Request $request): bool
        {
            return true;
        }

        public function fallback(Request $request): mixed
        {
            return null;
        }
    };

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
                    return 'fr';
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

    $middleware = new NegotiateContext([$versionDimension, $localeDimension], $context);

    $request = Request::create('/test');
    $route = new Route(['GET'], '/test', []);
    $route->defaults('content_accord.only', ['locale']);
    $request->setRouteResolver(fn () => $route);

    $middleware->handle($request, fn ($req) => response('OK'));

    expect($context->has('version'))->toBeFalse()
        ->and($context->has('locale'))->toBeTrue()
        ->and($context->get('locale'))->toBe('fr');
});

#[ApiNegotiate(only: ['locale'])]
class NegotiateContextAttributeTestController
{
    public function index(): string
    {
        return 'ok';
    }
}

test('#[ApiNegotiate] attribute filters dimensions via controller reflection', function () {
    $context = new NegotiatedContext();

    $versionDimension = new class implements NegotiationDimension
    {
        public function key(): string
        {
            return 'version';
        }

        public function resolver(): ContextResolver
        {
            throw new RuntimeException('Version resolver should not be called.');
        }

        public function validate(mixed $resolved, Request $request): bool
        {
            return true;
        }

        public function fallback(Request $request): mixed
        {
            return null;
        }
    };

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

    $middleware = new NegotiateContext([$versionDimension, $localeDimension], $context);

    $request = Request::create('/test');
    $route = new Route(['GET'], '/test', []);
    $action = $route->getAction();
    $action['controller'] = NegotiateContextAttributeTestController::class . '@index';
    $route->setAction($action);
    $request->setRouteResolver(fn () => $route);

    $middleware->handle($request, fn ($req) => response('OK'));

    expect($context->has('version'))->toBeFalse()
        ->and($context->has('locale'))->toBeTrue()
        ->and($context->get('locale'))->toBe('en');
});
