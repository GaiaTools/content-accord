<?php

namespace GaiaTools\ContentAccord\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final readonly class ApiFallback
{
    public function __construct(
        public bool $enabled = true,
    ) {
    }
}
