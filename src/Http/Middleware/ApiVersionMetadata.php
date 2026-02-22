<?php

namespace GaiaTools\ContentAccord\Http\Middleware;

use Closure;
use GaiaTools\ContentAccord\Attributes\ApiDeprecate;
use GaiaTools\ContentAccord\Attributes\ApiFallback;
use GaiaTools\ContentAccord\Attributes\ApiVersion as ApiVersionAttribute;
use GaiaTools\ContentAccord\Attributes\MapToVersion;
use GaiaTools\ContentAccord\ValueObjects\ApiVersion;
use Illuminate\Http\Request;
use ReflectionAttribute;
use ReflectionClass;
use Throwable;

final readonly class ApiVersionMetadata
{
    public function handle(Request $request, Closure $next, string ...$params): mixed
    {
        $metadata = self::parseParameters($params);
        $version = $metadata['version'] ?? null;
        $deprecated = $metadata['deprecated'] ?? null;
        $sunsetDate = $metadata['sunsetDate'] ?? null;
        $deprecationLink = $metadata['deprecationLink'] ?? null;
        $fallbackEnabled = $metadata['fallbackEnabled'] ?? null;

        $route = $request->route();

        if (! $route) {
            return $next($request);
        }

        $action = $route->getAction();
        if (! is_array($action)) {
            $action = [];
        }
        $controller = $action['controller'] ?? null;

        $resolvedVersion = null;
        /** @var array{deprecated: ?bool, sunset: ?string, link: ?string, fallback: ?bool} $resolvedAttributeMetadata */
        $resolvedAttributeMetadata = [];
        if (is_string($controller)) {
            [$class, $method] = $this->parseControllerAction($controller);
            if (class_exists($class)) {
                $resolvedVersion = $this->resolveAttributeVersion($class, $method, $version);
                $resolvedAttributeMetadata = $this->resolveAttributeMetadata($class, $method);
            }
        }

        if ($resolvedVersion) {
            $action['api_version'] = $resolvedVersion;
        } elseif ($version) {
            $action['api_version'] = $version;
        }

        if ($deprecated !== null) {
            $action['deprecated'] = $deprecated;
        }

        if ($sunsetDate) {
            $action['sunset'] = $sunsetDate;
        }

        if ($deprecationLink) {
            $action['deprecation_link'] = $deprecationLink;
        }

        if ($fallbackEnabled !== null) {
            $action['fallback_enabled'] = $fallbackEnabled;
        }

        // Attribute wins over middleware params
        if (($resolvedAttributeMetadata['deprecated'] ?? null) !== null) {
            $action['deprecated'] = $resolvedAttributeMetadata['deprecated'];
        }

        if (($resolvedAttributeMetadata['sunset'] ?? null) !== null) {
            $action['sunset'] = $resolvedAttributeMetadata['sunset'];
        }

        if (($resolvedAttributeMetadata['link'] ?? null) !== null) {
            $action['deprecation_link'] = $resolvedAttributeMetadata['link'];
        }

        if (($resolvedAttributeMetadata['fallback'] ?? null) !== null) {
            $action['fallback_enabled'] = $resolvedAttributeMetadata['fallback'];
        }

        $route->setAction($action);

        return $next($request);
    }

    /**
     * @return array{deprecated: ?bool, sunset: ?string, link: ?string, fallback: ?bool}
     */
    private function resolveAttributeMetadata(string $class, string $method): array
    {
        /** @var class-string $class */
        $classReflection = new ReflectionClass($class);

        $classDeprecate = $this->firstAttributeInstance($classReflection->getAttributes(ApiDeprecate::class));
        $classFallback = $this->firstAttributeInstance($classReflection->getAttributes(ApiFallback::class));

        $methodDeprecate = null;
        $methodFallback = null;

        if ($classReflection->hasMethod($method)) {
            $methodReflection = $classReflection->getMethod($method);
            $methodDeprecate = $this->firstAttributeInstance($methodReflection->getAttributes(ApiDeprecate::class));
            $methodFallback = $this->firstAttributeInstance($methodReflection->getAttributes(ApiFallback::class));
        }

        $deprecate = $methodDeprecate ?? $classDeprecate;
        $fallback = $methodFallback ?? $classFallback;

        return [
            'deprecated' => $deprecate?->deprecated,
            'sunset' => $deprecate?->sunset,
            'link' => $deprecate?->link,
            'fallback' => $fallback?->enabled,
        ];
    }

    private function resolveAttributeVersion(string $class, string $method, ?string $groupVersion): ?string
    {
        /** @var class-string $class */
        $classReflection = new ReflectionClass($class);
        $classVersion = $this->firstAttributeVersion($classReflection->getAttributes(ApiVersionAttribute::class));

        $methodVersion = null;
        if ($classReflection->hasMethod($method)) {
            $methodReflection = $classReflection->getMethod($method);
            $methodVersion = $this->firstAttributeVersion($methodReflection->getAttributes(MapToVersion::class));

            if ($methodVersion === null) {
                $methodVersion = $this->firstAttributeVersion($methodReflection->getAttributes(ApiVersionAttribute::class));
            }
        }

        $resolved = $methodVersion ?? $classVersion;

        if ($resolved) {
            $this->warnOnVersionMismatch($resolved, $class, $method, $groupVersion);
        }

        return $resolved;
    }

    /**
     * @template T of object
     * @param  array<int, ReflectionAttribute<T>>  $attributes
     * @return T|null
     */
    private function firstAttributeInstance(array $attributes): ?object
    {
        return $attributes !== [] ? $attributes[0]->newInstance() : null;
    }

    /**
     * @param  array<int, ReflectionAttribute<ApiVersionAttribute|MapToVersion>>  $attributes
     */
    private function firstAttributeVersion(array $attributes): ?string
    {
        if ($attributes === []) {
            return null;
        }

        $instance = $attributes[0]->newInstance();

        return $instance->version ?? null;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function parseControllerAction(string $controller): array
    {
        if (str_contains($controller, '@')) {
            $parts = array_pad(explode('@', $controller, 2), 2, '__invoke');
            /** @var array{0: string, 1: string} $parts */
            return $parts;
        }

        return [$controller, '__invoke'];
    }

    private function warnOnVersionMismatch(string $resolvedVersion, string $class, string $method, ?string $groupVersion): void
    {
        try {
            $groupVersion = $groupVersion ? ApiVersion::parse($groupVersion) : null;
            $attributeVersion = ApiVersion::parse($resolvedVersion);
        } catch (Throwable) {
            return;
        }

        if (! $groupVersion || $groupVersion->major === $attributeVersion->major) {
            return;
        }

        if (! app()->bound('log')) {
            return;
        }

        if (! app()->environment(['local', 'testing', 'development'])) {
            return;
        }

        app('log')->warning('ContentAccord: Attribute version mismatch detected.', [
            'group_version' => $groupVersion->toString(),
            'attribute_version' => $resolvedVersion,
            'controller' => $class,
            'method' => $method,
        ]);
    }

    /**
     * @param  string[]  $params
     * @return array{version?: string|null, deprecated?: bool|null, sunsetDate?: string|null, deprecationLink?: string|null, fallbackEnabled?: bool|null}
     */
    public static function parseParameters(array $params): array
    {
        $params = array_values($params);

        if ($params === []) {
            return [];
        }

        $hasNamed = false;
        foreach ($params as $param) {
            if (str_contains($param, '=')) {
                $hasNamed = true;
                break;
            }
        }

        if ($hasNamed) {
            $resolved = [];

            foreach ($params as $param) {
                if ($param === '' || ! str_contains($param, '=')) {
                    continue;
                }

                [$key, $value] = explode('=', $param, 2);
                $key = strtolower(trim($key));
                $value = trim($value);

                if ($value === '') {
                    continue;
                }

                switch ($key) {
                    case 'version':
                    case 'v':
                        $resolved['version'] = $value;
                        break;
                    case 'deprecated':
                    case 'deprecate':
                        $resolved['deprecated'] = self::normalizeBool($value);
                        break;
                    case 'sunset':
                        $resolved['sunsetDate'] = $value;
                        break;
                    case 'link':
                    case 'deprecation_link':
                        $resolved['deprecationLink'] = $value;
                        break;
                    case 'fallback':
                    case 'fallback_enabled':
                        $resolved['fallbackEnabled'] = self::normalizeBool($value);
                        break;
                }
            }

            return $resolved;
        }

        return [
            'version' => $params[0] !== '' ? $params[0] : null,
            'deprecated' => isset($params[1]) ? self::normalizeBool($params[1]) : null,
            'sunsetDate' => isset($params[2]) && $params[2] !== '' ? $params[2] : null,
            'deprecationLink' => isset($params[3]) && $params[3] !== '' ? $params[3] : null,
            'fallbackEnabled' => isset($params[4]) ? self::normalizeBool($params[4]) : null,
        ];
    }

    private static function normalizeBool(?string $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        $value = strtolower(trim($value));

        if ($value === '') {
            return null;
        }

        return match ($value) {
            '1', 'true', 'yes', 'on' => true,
            '0', 'false', 'no', 'off' => false,
            default => null,
        };
    }
}
