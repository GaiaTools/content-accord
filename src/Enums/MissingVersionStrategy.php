<?php

namespace GaiaTools\ContentAccord\Enums;

enum MissingVersionStrategy: string
{
    case Reject = 'reject';
    case DefaultVersion = 'default';
    case LatestVersion = 'latest';
    case Require = 'require';
}
