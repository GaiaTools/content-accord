<?php

namespace GaiaTools\ContentAccord\Resolvers\Locale;

use GaiaTools\ContentAccord\Contracts\ContextResolver;
use Illuminate\Http\Request;

final readonly class AcceptLanguageLocaleResolver implements ContextResolver
{
    public function resolve(Request $request): ?string
    {
        $header = $request->header('Accept-Language');

        if (! is_string($header) || $header === '') {
            return null;
        }

        return $this->firstTag($header);
    }

    private function firstTag(string $header): ?string
    {
        foreach (explode(',', $header) as $entry) {
            $tag = trim(explode(';', $entry)[0]);

            if ($tag !== '' && $tag !== '*') {
                return $tag;
            }
        }

        return null;
    }
}
