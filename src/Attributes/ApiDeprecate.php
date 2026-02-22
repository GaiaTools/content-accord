<?php

namespace GaiaTools\ContentAccord\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final readonly class ApiDeprecate
{
    public function __construct(
        public bool $deprecated = true,
        public ?string $sunset = null,
        public ?string $link = null,
    ) {}
}
