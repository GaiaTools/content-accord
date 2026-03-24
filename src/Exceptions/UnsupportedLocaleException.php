<?php

namespace GaiaTools\ContentAccord\Exceptions;

use Illuminate\Http\JsonResponse;

class UnsupportedLocaleException extends NegotiationException
{
    public function render(): JsonResponse
    {
        return new JsonResponse(['message' => $this->getMessage()], 406);
    }

    /**
     * @param  array<int, string>  $supportedLocales
     */
    public static function forLocale(mixed $locale, array $supportedLocales = []): self
    {
        $label = is_string($locale) && $locale !== '' ? $locale : '(none)';
        $message = "Unsupported locale: {$label}";

        if (! empty($supportedLocales)) {
            $supported = implode(', ', $supportedLocales);
            $message .= ". Supported locales: {$supported}";
        }

        return new self($message);
    }
}
