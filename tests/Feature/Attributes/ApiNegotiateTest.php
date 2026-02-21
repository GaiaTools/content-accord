<?php

use GaiaTools\ContentAccord\Attributes\ApiNegotiate;
use GaiaTools\ContentAccord\Contracts\ContextResolver;
use GaiaTools\ContentAccord\Contracts\NegotiationDimension;
use GaiaTools\ContentAccord\Http\Middleware\NegotiateContext;
use GaiaTools\ContentAccord\Http\NegotiatedContext;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;

// Controller that restricts negotiation to only 'version'
#[ApiNegotiate(only: ['version'])]
class ApiNegotiateOnlyVersionController
{
    public function index(): string
    {
        return 'ok';
    }
}

// Controller that skips 'locale' dimension
#[ApiNegotiate(skip: ['locale'])]
class ApiNegotiateSkipLocaleController
{
    public function index(): string
    {
        return 'ok';
    }
}

// Class attribute: only=['version']; method attribute wins: skip=['locale']
#[ApiNegotiate(only: ['version'])]
class ApiNegotiateMethodOverridesClassController
{
    #[ApiNegotiate(skip: ['locale'])]
    public function index(): string
    {
        return 'ok';
    }
}

function makeNegotiateDimension(string $key, string $resolvedValue): NegotiationDimension
{
    return new class ($key, $resolvedValue) implements NegotiationDimension
    {
        public function __construct(
            private string $dimKey,
            private string $value,
        ) {
        }

        public function key(): string
        {
            return $this->dimKey;
        }

        public function resolver(): ContextResolver
        {
            $value = $this->value;

            return new class ($value) implements ContextResolver
            {
                public function __construct(private string $value)
                {
                }

                public function resolve(Request $request): mixed
                {
                    return $this->value;
                }
            };
        }

        public function validate(mixed $resolved, Request $request): bool
        {
            return true;
        }

        public function fallback(Request $request): mixed
        {
            return $this->value;
        }
    };
}

function makeRouteWithController(string $controllerClass, string $method = 'index'): Route
{
    $route = new Route(['GET'], '/test', []);
    $action = $route->getAction();
    $action['controller'] = $controllerClass . '@' . $method;
    $route->setAction($action);

    return $route;
}

test('#[ApiNegotiate(only: [...])] limits negotiation to specified dimensions', function () {
    $context = new NegotiatedContext();
    $versionDim = makeNegotiateDimension('version', 'v1');
    $localeDim = makeNegotiateDimension('locale', 'en');

    $middleware = new NegotiateContext([$versionDim, $localeDim], $context);

    $request = Request::create('/test');
    $route = makeRouteWithController(ApiNegotiateOnlyVersionController::class);
    $request->setRouteResolver(fn () => $route);

    $middleware->handle($request, fn ($req) => response('OK'));

    expect($context->has('version'))->toBeTrue()
        ->and($context->get('version'))->toBe('v1')
        ->and($context->has('locale'))->toBeFalse();
});

test('#[ApiNegotiate(skip: [...])] excludes specified dimensions', function () {
    $context = new NegotiatedContext();
    $versionDim = makeNegotiateDimension('version', 'v1');
    $localeDim = makeNegotiateDimension('locale', 'en');

    $middleware = new NegotiateContext([$versionDim, $localeDim], $context);

    $request = Request::create('/test');
    $route = makeRouteWithController(ApiNegotiateSkipLocaleController::class);
    $request->setRouteResolver(fn () => $route);

    $middleware->handle($request, fn ($req) => response('OK'));

    expect($context->has('version'))->toBeTrue()
        ->and($context->get('version'))->toBe('v1')
        ->and($context->has('locale'))->toBeFalse();
});

test('#[ApiNegotiate] on method overrides class attribute', function () {
    $context = new NegotiatedContext();
    $versionDim = makeNegotiateDimension('version', 'v1');
    $localeDim = makeNegotiateDimension('locale', 'en');

    $middleware = new NegotiateContext([$versionDim, $localeDim], $context);

    $request = Request::create('/test');
    // Method has skip=['locale'], class has only=['version']. Method wins.
    $route = makeRouteWithController(ApiNegotiateMethodOverridesClassController::class);
    $request->setRouteResolver(fn () => $route);

    $middleware->handle($request, fn ($req) => response('OK'));

    // Method attribute (skip=['locale']) wins: version is NOT restricted to only=['version'],
    // locale is skipped. Both would run except locale is skipped.
    expect($context->has('version'))->toBeTrue()
        ->and($context->has('locale'))->toBeFalse();
});

test('#[ApiNegotiate] attribute wins over route default', function () {
    $context = new NegotiatedContext();
    $versionDim = makeNegotiateDimension('version', 'v1');
    $localeDim = makeNegotiateDimension('locale', 'en');

    $middleware = new NegotiateContext([$versionDim, $localeDim], $context);

    $request = Request::create('/test');
    $route = makeRouteWithController(ApiNegotiateOnlyVersionController::class);
    // Route default says only=['locale'] but attribute says only=['version']
    $route->defaults('content_accord.only', ['locale']);
    $request->setRouteResolver(fn () => $route);

    $middleware->handle($request, fn ($req) => response('OK'));

    // Attribute wins: only version is processed, not locale
    expect($context->has('version'))->toBeTrue()
        ->and($context->has('locale'))->toBeFalse();
});

test('#[ApiNegotiate] class-level attribute applies when method has no attribute', function () {
    $context = new NegotiatedContext();
    $versionDim = makeNegotiateDimension('version', 'v1');
    $localeDim = makeNegotiateDimension('locale', 'en');

    $middleware = new NegotiateContext([$versionDim, $localeDim], $context);

    $request = Request::create('/test');
    // ApiNegotiateOnlyVersionController has class-level #[ApiNegotiate(only: ['version'])]
    // and the index method has no method-level attribute, so class attribute applies
    $route = makeRouteWithController(ApiNegotiateOnlyVersionController::class, 'index');
    $request->setRouteResolver(fn () => $route);

    $middleware->handle($request, fn ($req) => response('OK'));

    expect($context->has('version'))->toBeTrue()
        ->and($context->has('locale'))->toBeFalse();
});
