<?php

namespace GaiaTools\ContentAccord\Contracts;

use Illuminate\Http\Request;

interface ContextResolver
{
    /**
     * Attempt to resolve a value from the request.
     * Returns null if this resolver cannot determine the value.
     */
    public function resolve(Request $request): mixed;
}
