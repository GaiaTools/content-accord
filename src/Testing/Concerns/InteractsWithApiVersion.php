<?php

namespace GaiaTools\ContentAccord\Testing\Concerns;

use GaiaTools\ContentAccord\Testing\ApiVersionRequestBuilder;

trait InteractsWithApiVersion
{
    public function withApiVersion(string $version): ApiVersionRequestBuilder
    {
        return new ApiVersionRequestBuilder($this, $version);
    }
}
