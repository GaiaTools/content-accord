<?php

namespace GaiaTools\ContentAccord\Dimensions;

use GaiaTools\ContentAccord\Contracts\ContextResolver;
use GaiaTools\ContentAccord\Contracts\NegotiationDimension;
use GaiaTools\ContentAccord\Exceptions\MissingLocaleException;
use GaiaTools\ContentAccord\Exceptions\UnsupportedLocaleException;
use Illuminate\Http\Request;

final readonly class LocaleDimension implements NegotiationDimension
{
    /**
     * @param  array<int, string>  $supportedLocales
     */
    public function __construct(
        private ContextResolver $resolver,
        private string $default,
        private array $supportedLocales,
    ) {}

    public function key(): string
    {
        return 'locale';
    }

    public function resolver(): ContextResolver
    {
        return $this->resolver;
    }

    public function validate(mixed $resolved, Request $request): bool
    {
        if (! is_string($resolved) || $resolved === '') {
            throw UnsupportedLocaleException::forLocale($resolved, $this->supportedLocales);
        }

        $normalizedResolved = strtolower($resolved);
        $normalizedSupported = array_map('strtolower', $this->supportedLocales);

        if ($this->supportedLocales !== [] && ! in_array($normalizedResolved, $normalizedSupported, true)) {
            throw UnsupportedLocaleException::forLocale($resolved, $this->supportedLocales);
        }

        return true;
    }

    public function fallback(Request $request): mixed
    {
        if ($this->default === '') {
            throw new MissingLocaleException;
        }

        return $this->default;
    }
}
