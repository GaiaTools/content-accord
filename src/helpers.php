<?php

use GaiaTools\ContentAccord\Http\NegotiatedContext;
use GaiaTools\ContentAccord\ValueObjects\ApiVersion;

if (! function_exists('apiVersion')) {
    function apiVersion(): ?ApiVersion
    {
        return app(NegotiatedContext::class)->get('version');
    }
}
