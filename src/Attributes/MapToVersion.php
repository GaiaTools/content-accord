<?php

namespace GaiaTools\ContentAccord\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final readonly class MapToVersion
{
    public function __construct(
        public string $version,
    ) {
    }
}
