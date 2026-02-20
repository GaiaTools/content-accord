<?php

namespace GaiaTools\ContentAccord\Contracts;

use GaiaTools\ContentAccord\ValueObjects\ApiVersion;
use Illuminate\Http\Request;

interface VersionResolver extends ContextResolver
{
    /**
     * @return ApiVersion|null
     */
    public function resolve(Request $request): mixed;
}
