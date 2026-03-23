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
    $context = new NegotiatedContext;
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
    $context = new NegotiatedContext;
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
    $context = new NegotiatedContext;
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
    $context = new NegotiatedContext;
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
    $context = new NegotiatedContext;

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
    $context = new NegotiatedContext;
    $middleware = new NegotiateContext([], $context);

    $request = Request::create('/test');

    $response = $middleware->handle($request, fn ($req) => response('OK'));

    expect($context->all())->toBeEmpty();
});

test('calls next closure and returns response', function () {
    $context = new NegotiatedContext;
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
    $context = new NegotiatedContext;

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
    $context = new NegotiatedContext;

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

test('resolveNegotiateAttribute handles non-array route action gracefully', function () {
    $context = new NegotiatedContext;
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

    // Mock a Route whose getAction() returns non-array — triggers line 95
    $mockRoute = Mockery::mock(Route::class);
    $mockRoute->defaults = [];
    $mockRoute->shouldReceive('getAction')->withNoArgs()->andReturn('not-an-array');
    $request->setRouteResolver(fn () => $mockRoute);

    $middleware->handle($request, fn ($req) => response('OK'));

    // Dimension still runs; null controller means attribute skipped
    expect($context->has('version'))->toBeTrue();
});

test('resolveNegotiateAttribute returns null when route is not a Route instance', function () {
    $context = new NegotiatedContext;
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

    // Use a stdClass with a 'defaults' property — looks enough like a route to pass the
    // `if (! $route)` check but is NOT an Illuminate Route, covering line 90.
    $fakeRoute = new stdClass;
    $fakeRoute->defaults = [];
    $request->setRouteResolver(fn () => $fakeRoute);

    $middleware->handle($request, fn ($req) => response('OK'));

    expect($context->has('version'))->toBeTrue();
});

test('resolveNegotiateAttribute returns null when controller action is not a string', function () {
    $context = new NegotiatedContext;
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
    $route = new Route(['GET'], '/test', []);
    // Set a non-string controller
    $action = $route->getAction();
    $action['controller'] = null;
    $route->setAction($action);
    $request->setRouteResolver(fn () => $route);

    $middleware->handle($request, fn ($req) => response('OK'));

    expect($context->has('version'))->toBeTrue();
});

test('resolveNegotiateAttribute returns null when class does not exist', function () {
    $context = new NegotiatedContext;
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
    $route = new Route(['GET'], '/test', []);
    $action = $route->getAction();
    $action['controller'] = 'NonExistentClass@method';
    $route->setAction($action);
    $request->setRouteResolver(fn () => $route);

    $middleware->handle($request, fn ($req) => response('OK'));

    expect($context->has('version'))->toBeTrue();
});

test('normalizeDimensionList returns null for empty array', function () {
    $context = new NegotiatedContext;
    $middleware = new NegotiateContext([], $context);

    $request = Request::create('/test');
    $route = new Route(['GET'], '/test', []);
    // Pass empty array as only — should treat as no filter (null)
    $route->defaults('content_accord.only', []);
    $request->setRouteResolver(fn () => $route);

    $middleware->handle($request, fn ($req) => response('OK'));

    expect($context->all())->toBeEmpty();
});

test('normalizeDimensionList handles string value for only filter', function () {
    $context = new NegotiatedContext;

    $localeDimension = new class implements NegotiationDimension
    {
        public function key(): string { return 'locale'; }

        public function resolver(): ContextResolver
        {
            return new class implements ContextResolver
            {
                public function resolve(Request $request): mixed { return 'en'; }
            };
        }

        public function validate(mixed $resolved, Request $request): bool { return true; }

        public function fallback(Request $request): mixed { return 'en'; }
    };

    $middleware = new NegotiateContext([$localeDimension], $context);

    $request = Request::create('/test');
    $route = new Route(['GET'], '/test', []);
    // String value — should be treated as [$value]
    $route->defaults('content_accord.only', 'locale');
    $request->setRouteResolver(fn () => $route);

    $middleware->handle($request, fn ($req) => response('OK'));

    expect($context->has('locale'))->toBeTrue();
});

test('normalizeDimensionList returns null for non-string non-array value', function () {
    $context = new NegotiatedContext;
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
    $route = new Route(['GET'], '/test', []);
    // Integer value for only — should be ignored (treated as null)
    $route->defaults('content_accord.only', 42);
    $request->setRouteResolver(fn () => $route);

    $middleware->handle($request, fn ($req) => response('OK'));

    // With only=null all dimensions run including version
    expect($context->has('version'))->toBeTrue();
});

#[ApiNegotiate(only: ['locale'])]
class NegotiateContextAttributeTestController
{
    public function index(): string
    {
        return 'ok';
    }
}

#[ApiNegotiate(only: ['locale'])]
class NegotiateContextInvokableAttributeController
{
    public function __invoke(): string
    {
        return 'ok';
    }
}

test('#[ApiNegotiate] on invokable controller filters dimensions (no @ in controller string)', function () {
    $context = new NegotiatedContext;

    $versionDimension = new class implements NegotiationDimension
    {
        public function key(): string { return 'version'; }

        public function resolver(): ContextResolver
        {
            throw new RuntimeException('Version resolver should not be called.');
        }

        public function validate(mixed $resolved, Request $request): bool { return true; }

        public function fallback(Request $request): mixed { return null; }
    };

    $localeDimension = new class implements NegotiationDimension
    {
        public function key(): string { return 'locale'; }

        public function resolver(): ContextResolver
        {
            return new class implements ContextResolver
            {
                public function resolve(Request $request): mixed { return 'en'; }
            };
        }

        public function validate(mixed $resolved, Request $request): bool { return true; }

        public function fallback(Request $request): mixed { return 'en'; }
    };

    $middleware = new NegotiateContext([$versionDimension, $localeDimension], $context);

    $request = Request::create('/test');
    $route = new Route(['GET'], '/test', []);
    $action = $route->getAction();
    // No '@' in controller string → parseControllerAction takes the '__invoke' branch (line 145)
    $action['controller'] = NegotiateContextInvokableAttributeController::class;
    $route->setAction($action);
    $request->setRouteResolver(fn () => $route);

    $middleware->handle($request, fn ($req) => response('OK'));

    expect($context->has('version'))->toBeFalse()
        ->and($context->has('locale'))->toBeTrue();
});

test('#[ApiNegotiate] attribute filters dimensions via controller reflection', function () {
    $context = new NegotiatedContext;

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
    $action['controller'] = NegotiateContextAttributeTestController::class.'@index';
    $route->setAction($action);
    $request->setRouteResolver(fn () => $route);

    $middleware->handle($request, fn ($req) => response('OK'));

    expect($context->has('version'))->toBeFalse()
        ->and($context->has('locale'))->toBeTrue()
        ->and($context->get('locale'))->toBe('en');
});
