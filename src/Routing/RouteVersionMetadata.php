<?php

namespace GaiaTools\ContentAccord\Routing;

use GaiaTools\ContentAccord\Attributes\ApiDeprecate;
use GaiaTools\ContentAccord\Attributes\ApiFallback;
use GaiaTools\ContentAccord\Attributes\ApiVersion as ApiVersionAttribute;
use GaiaTools\ContentAccord\Attributes\MapToVersion;
use GaiaTools\ContentAccord\Http\Middleware\ApiVersionMetadata;
use GaiaTools\ContentAccord\ValueObjects\ApiVersion;
use Illuminate\Routing\Route;
use ReflectionAttribute;
use ReflectionClass;
use Throwable;

final class RouteVersionMetadata
{
    /**
     * @param  array<string, mixed>  $config
     * @return array{version?: string, deprecated?: bool, sunset?: string, deprecation_link?: string, fallback?: bool}
     */
    public static function resolve(Route $route, array $config = []): array
    {
        $action = $route->getAction();
        if (! is_array($action)) {
            $action = [];
        }

        $version = self::normalizeString($action['api_version'] ?? null);
        $deprecated = array_key_exists('deprecated', $action) ? (bool) $action['deprecated'] : null;
        $sunset = self::normalizeString($action['sunset'] ?? null);
        $link = self::normalizeString($action['deprecation_link'] ?? null);
        $fallback = array_key_exists('fallback_enabled', $action) ? (bool) $action['fallback_enabled'] : null;

        $middlewareMetadata = self::resolveFromMiddleware($route);

        if ($version === null && isset($middlewareMetadata['version'])) {
            $version = $middlewareMetadata['version'];
        }

        $attributeVersion = self::resolveAttributeVersion($route);
        if ($attributeVersion !== null) {
            $version = $attributeVersion;
        }

        if ($deprecated === null && array_key_exists('deprecated', $middlewareMetadata)) {
            $deprecated = $middlewareMetadata['deprecated'];
        }

        if ($sunset === null && isset($middlewareMetadata['sunset'])) {
            $sunset = $middlewareMetadata['sunset'];
        }

        if ($link === null && isset($middlewareMetadata['deprecation_link'])) {
            $link = $middlewareMetadata['deprecation_link'];
        }

        if ($fallback === null && array_key_exists('fallback', $middlewareMetadata)) {
            $fallback = $middlewareMetadata['fallback'];
        }

        if ($version !== null && $config !== []) {
            $configMetadata = self::resolveConfigMetadata($version, $config);

            if ($deprecated === null && array_key_exists('deprecated', $configMetadata)) {
                $deprecated = $configMetadata['deprecated'];
            }

            if ($sunset === null && isset($configMetadata['sunset'])) {
                $sunset = $configMetadata['sunset'];
            }

            if ($link === null && isset($configMetadata['deprecation_link'])) {
                $link = $configMetadata['deprecation_link'];
            }
        }

        if ($fallback === null && $config !== []) {
            $fallback = (bool) ($config['fallback'] ?? false);
        }

        // Attribute metadata wins over all other sources
        $attributeMetadata = self::resolveAttributeMetadata($route);

        if ($attributeMetadata['deprecated'] !== null) {
            $deprecated = $attributeMetadata['deprecated'];
        }

        if ($attributeMetadata['sunset'] !== null) {
            $sunset = $attributeMetadata['sunset'];
        }

        if ($attributeMetadata['link'] !== null) {
            $link = $attributeMetadata['link'];
        }

        if ($attributeMetadata['fallback'] !== null) {
            $fallback = $attributeMetadata['fallback'];
        }

        $resolved = [];

        if ($version !== null) {
            $resolved['version'] = $version;
        }

        if ($deprecated !== null) {
            $resolved['deprecated'] = $deprecated;
        }

        if ($sunset !== null) {
            $resolved['sunset'] = $sunset;
        }

        if ($link !== null) {
            $resolved['deprecation_link'] = $link;
        }

        if ($fallback !== null) {
            $resolved['fallback'] = $fallback;
        }

        return $resolved;
    }

    /**
     * @return array{version?: string, deprecated?: bool, sunset?: string, deprecation_link?: string, fallback?: bool}
     */
    private static function resolveFromMiddleware(Route $route): array
    {
        $middleware = $route->getAction('middleware') ?? [];

        if (is_string($middleware)) {
            $middleware = [$middleware];
        }

        if (! is_array($middleware)) {
            return [];
        }

        foreach ($middleware as $entry) {
            if (! is_string($entry) || $entry === '') {
                continue;
            }

            [$name, $paramString] = array_pad(explode(':', $entry, 2), 2, '');

            if ($name !== 'content-accord.version' && $name !== ApiVersionMetadata::class) {
                continue;
            }

            $params = $paramString === '' ? [] : explode(',', $paramString);
            $parsed = ApiVersionMetadata::parseParameters($params);

            $resolved = [];

            if (isset($parsed['version']) && $parsed['version'] !== '') {
                $resolved['version'] = $parsed['version'];
            }

            if (array_key_exists('deprecated', $parsed) && $parsed['deprecated'] !== null) {
                $resolved['deprecated'] = $parsed['deprecated'];
            }

            if (isset($parsed['sunsetDate']) && $parsed['sunsetDate'] !== '') {
                $resolved['sunset'] = $parsed['sunsetDate'];
            }

            if (isset($parsed['deprecationLink']) && $parsed['deprecationLink'] !== '') {
                $resolved['deprecation_link'] = $parsed['deprecationLink'];
            }

            if (array_key_exists('fallbackEnabled', $parsed) && $parsed['fallbackEnabled'] !== null) {
                $resolved['fallback'] = $parsed['fallbackEnabled'];
            }

            return $resolved;
        }

        return [];
    }

    /**
     * @return array{deprecated: ?bool, sunset: ?string, link: ?string, fallback: ?bool}
     */
    private static function resolveAttributeMetadata(Route $route): array
    {
        $action = $route->getAction();
        if (! is_array($action)) {
            $action = [];
        }
        $controller = $action['controller'] ?? null;

        if (! is_string($controller) || $controller === '') {
            return ['deprecated' => null, 'sunset' => null, 'link' => null, 'fallback' => null];
        }

        [$class, $method] = self::parseControllerAction($controller);

        if (! class_exists($class)) {
            return ['deprecated' => null, 'sunset' => null, 'link' => null, 'fallback' => null];
        }

        /** @var class-string $class */
        $classReflection = new ReflectionClass($class);

        $classDeprecate = self::firstAttributeInstance($classReflection->getAttributes(ApiDeprecate::class));
        $classFallback = self::firstAttributeInstance($classReflection->getAttributes(ApiFallback::class));

        $methodDeprecate = null;
        $methodFallback = null;

        if ($classReflection->hasMethod($method)) {
            $methodReflection = $classReflection->getMethod($method);
            $methodDeprecate = self::firstAttributeInstance($methodReflection->getAttributes(ApiDeprecate::class));
            $methodFallback = self::firstAttributeInstance($methodReflection->getAttributes(ApiFallback::class));
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

    private static function resolveAttributeVersion(Route $route): ?string
    {
        $action = $route->getAction();
        if (! is_array($action)) {
            $action = [];
        }
        $controller = $action['controller'] ?? null;

        if (! is_string($controller) || $controller === '') {
            return null;
        }

        [$class, $method] = self::parseControllerAction($controller);

        if (! class_exists($class)) {
            return null;
        }

        /** @var class-string $class */
        $classReflection = new ReflectionClass($class);
        $classVersion = self::firstAttributeVersion($classReflection->getAttributes(ApiVersionAttribute::class));

        $methodVersion = null;
        if ($classReflection->hasMethod($method)) {
            $methodReflection = $classReflection->getMethod($method);
            $methodVersion = self::firstAttributeVersion($methodReflection->getAttributes(MapToVersion::class));

            if ($methodVersion === null) {
                $methodVersion = self::firstAttributeVersion($methodReflection->getAttributes(ApiVersionAttribute::class));
            }
        }

        return $methodVersion ?? $classVersion;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{deprecated?: bool, sunset?: ?string, deprecation_link?: ?string}
     */
    private static function resolveConfigMetadata(string $version, array $config): array
    {
        try {
            $parsed = ApiVersion::parse($version);
        } catch (Throwable) {
            return [];
        }

        $versions = $config['versions'] ?? [];
        if (! is_array($versions)) {
            return [];
        }
        $metadata = $versions[(string) $parsed->major] ?? null;

        if (! is_array($metadata)) {
            return [];
        }

        $deprecated = (bool) ($metadata['deprecated'] ?? false);
        $sunset = isset($metadata['sunset']) && is_string($metadata['sunset']) ? $metadata['sunset'] : null;
        $link = isset($metadata['deprecation_link']) && is_string($metadata['deprecation_link'])
            ? $metadata['deprecation_link']
            : null;

        return [
            'deprecated' => $deprecated,
            'sunset' => $sunset,
            'deprecation_link' => $link,
        ];
    }

    /**
     * @template T of object
     * @param  array<int, ReflectionAttribute<T>>  $attributes
     * @return T|null
     */
    private static function firstAttributeInstance(array $attributes): ?object
    {
        return $attributes !== [] ? $attributes[0]->newInstance() : null;
    }

    /**
     * @param  array<int, ReflectionAttribute<ApiVersionAttribute|MapToVersion>>  $attributes
     */
    private static function firstAttributeVersion(array $attributes): ?string
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
    private static function parseControllerAction(string $controller): array
    {
        if (str_contains($controller, '@')) {
            $parts = array_pad(explode('@', $controller, 2), 2, '__invoke');
            /** @var array{0: string, 1: string} $parts */
            return $parts;
        }

        return [$controller, '__invoke'];
    }

    private static function normalizeString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
