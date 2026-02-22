<?php

namespace GaiaTools\ContentAccord\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final readonly class ApiNegotiate
{
    /**
     * @param  array<int, string>|null  $only
     * @param  array<int, string>|null  $skip
     */
    public function __construct(
        public ?array $only = null,
        public ?array $skip = null,
    ) {}
}
