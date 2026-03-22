<?php

namespace GaiaTools\ContentAccord\Events;

use GaiaTools\ContentAccord\ValueObjects\ApiVersion;
use Illuminate\Http\Request;

final readonly class VersionNegotiated
{
    public function __construct(
        public ApiVersion $version,
        public Request $request,
    ) {}
}
