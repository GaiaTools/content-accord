<?php

namespace GaiaTools\ContentAccord\Exceptions;

use Illuminate\Http\JsonResponse;

class MissingLocaleException extends NegotiationException
{
    public function __construct(string $message = 'Locale is required but was not provided')
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return new JsonResponse(['message' => $this->getMessage()], 406);
    }
}
