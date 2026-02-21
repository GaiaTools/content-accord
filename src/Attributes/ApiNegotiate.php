<?php

namespace GaiaTools\ContentAccord\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final readonly class ApiNegotiate
{
    public function __construct(
        public ?array $only = null,
        public ?array $skip = null,
    ) {
    }
}
