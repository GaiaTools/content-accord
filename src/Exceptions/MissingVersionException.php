<?php

namespace GaiaTools\ContentAccord\Exceptions;

class MissingVersionException extends ApiVersionException
{
    public function __construct(string $message = 'API version is required but was not provided')
    {
        parent::__construct($message);
    }
}
