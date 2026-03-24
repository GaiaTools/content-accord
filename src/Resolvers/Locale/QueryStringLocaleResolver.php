<?php

namespace GaiaTools\ContentAccord\Resolvers\Locale;

use GaiaTools\ContentAccord\Contracts\ContextResolver;
use Illuminate\Http\Request;

final readonly class QueryStringLocaleResolver implements ContextResolver
{
    public function __construct(
        private string $parameter = 'locale',
    ) {}

    public function resolve(Request $request): ?string
    {
        $value = $request->query($this->parameter);

        if (! is_string($value) || $value === '') {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }
}
