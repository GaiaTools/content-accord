<?php

use GaiaTools\ContentAccord\Http\NegotiatedContext;
use GaiaTools\ContentAccord\ValueObjects\ApiVersion;

if (! function_exists('apiVersion')) {
    /**
     * Get the current API version from the negotiated context.
     */
    function apiVersion(): ?ApiVersion
    {
        try {
            $context = app(NegotiatedContext::class);

            return $context->get('version');
        } catch (Throwable) {
            return null;
        }
    }
}
