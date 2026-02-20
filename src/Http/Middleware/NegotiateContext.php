<?php

namespace GaiaTools\ContentAccord\Http\Middleware;

use GaiaTools\ContentAccord\Contracts\NegotiationDimension;
use GaiaTools\ContentAccord\Http\NegotiatedContext;
use Closure;
use Illuminate\Http\Request;

final readonly class NegotiateContext
{
    /**
     * @param  NegotiationDimension[]  $dimensions
     */
    public function __construct(
        private array $dimensions,
        private NegotiatedContext $context,
    ) {
    }

    public function handle(Request $request, Closure $next): mixed
    {
        foreach ($this->dimensions as $dimension) {
            $resolved = $dimension->resolver()->resolve($request);

            if ($resolved === null) {
                $resolved = $dimension->fallback($request);
            }

            $dimension->validate($resolved, $request);

            $this->context->set($dimension->key(), $resolved);
        }

        return $next($request);
    }
}
