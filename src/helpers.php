<?php

use GaiaTools\ContentAccord\Http\NegotiatedContext;
use GaiaTools\ContentAccord\ValueObjects\ApiVersion;

if (! function_exists('apiVersion')) {
    function apiVersion(): ?ApiVersion
    {
        $value = app(NegotiatedContext::class)->get('version');

        return $value instanceof ApiVersion ? $value : null;
    }
}
