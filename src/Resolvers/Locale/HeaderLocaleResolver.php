<?php

namespace GaiaTools\ContentAccord\Resolvers\Locale;

use GaiaTools\ContentAccord\Contracts\ContextResolver;
use Illuminate\Http\Request;

final readonly class HeaderLocaleResolver implements ContextResolver
{
    public function __construct(
        private string $headerName = 'X-Locale',
    ) {}

    public function resolve(Request $request): ?string
    {
        $value = $request->header($this->headerName);

        if (! is_string($value) || $value === '') {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }
}
