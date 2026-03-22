<?php

namespace GaiaTools\ContentAccord\Events;

use GaiaTools\ContentAccord\ValueObjects\ApiVersion;
use Illuminate\Http\Request;

final readonly class DeprecatedVersionAccessed
{
    public function __construct(
        public ApiVersion $version,
        public Request $request,
    ) {}
}
