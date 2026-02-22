<?php

namespace GaiaTools\ContentAccord\Http\Middleware;

use Closure;
use GaiaTools\ContentAccord\Attributes\ApiNegotiate;
use GaiaTools\ContentAccord\Contracts\NegotiationDimension;
use GaiaTools\ContentAccord\Http\NegotiatedContext;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use ReflectionClass;

final readonly class NegotiateContext
{
    /**
     * @param  NegotiationDimension[]  $dimensions
     */
    public function __construct(
        private array $dimensions,
        private NegotiatedContext $context,
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        foreach ($this->resolveDimensions($request) as $dimension) {
            $resolved = $dimension->resolver()->resolve($request);

            if ($resolved === null) {
                $resolved = $dimension->fallback($request);
            }

            $dimension->validate($resolved, $request);

            $this->context->set($dimension->key(), $resolved);
        }

        return $next($request);
    }

    /**
     * @return NegotiationDimension[]
     */
    private function resolveDimensions(Request $request): array
    {
        $route = $request->route();

        if (! $route) {
            return $this->dimensions;
        }

        $defaults = is_array($route->defaults ?? null) ? $route->defaults : [];
        $attr = $this->resolveNegotiateAttribute($route);
        $only = $this->normalizeDimensionList($attr?->only ?? $defaults['content_accord.only'] ?? null);
        $skip = $this->normalizeDimensionList($attr?->skip ?? $defaults['content_accord.skip'] ?? null);

        $dimensions = $this->dimensions;

        if ($only !== null) {
            $dimensions = array_values(array_filter(
                $dimensions,
                fn (NegotiationDimension $dimension) => in_array($dimension->key(), $only, true)
            ));
        }

        if ($skip !== null) {
            $dimensions = array_values(array_filter(
                $dimensions,
                fn (NegotiationDimension $dimension) => ! in_array($dimension->key(), $skip, true)
            ));
        }

        return $dimensions;
    }

    private function resolveNegotiateAttribute(mixed $route): ?ApiNegotiate
    {
        if (! ($route instanceof Route)) {
            return null;
        }

        $action = $route->getAction();
        $controller = $action['controller'] ?? null;

        if (! is_string($controller) || $controller === '') {
            return null;
        }

        [$class, $method] = $this->parseControllerAction($controller);

        if (! class_exists($class)) {
            return null;
        }

        $classReflection = new ReflectionClass($class);
        $classAttr = $this->firstAttributeInstance($classReflection->getAttributes(ApiNegotiate::class));

        $methodAttr = null;
        if ($classReflection->hasMethod($method)) {
            $methodReflection = $classReflection->getMethod($method);
            $methodAttr = $this->firstAttributeInstance($methodReflection->getAttributes(ApiNegotiate::class));
        }

        return $methodAttr ?? $classAttr;
    }

    private function firstAttributeInstance(array $attributes): ?object
    {
        return $attributes !== [] ? $attributes[0]->newInstance() : null;
    }

    private function parseControllerAction(string $controller): array
    {
        if (str_contains($controller, '@')) {
            return explode('@', $controller, 2);
        }

        return [$controller, '__invoke'];
    }

    /**
     * @return string[]|null
     */
    private function normalizeDimensionList(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value) && $value !== '') {
            return [$value];
        }

        if (is_array($value)) {
            $filtered = array_values(array_filter($value, fn ($item) => is_string($item) && $item !== ''));

            return $filtered === [] ? null : $filtered;
        }

        return null;
    }
}
