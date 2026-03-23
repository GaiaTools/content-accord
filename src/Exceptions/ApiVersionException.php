<?php

namespace GaiaTools\ContentAccord\Exceptions;

use Illuminate\Http\JsonResponse;

class ApiVersionException extends NegotiationException
{
    public function render(): JsonResponse
    {
        return new JsonResponse(['message' => $this->getMessage()], 406);
    }
}
