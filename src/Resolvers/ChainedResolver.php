<?php

namespace GaiaTools\ContentAccord\Resolvers;

use GaiaTools\ContentAccord\Contracts\ContextResolver;
use Illuminate\Http\Request;

final readonly class ChainedResolver implements ContextResolver
{
    /**
     * @param  ContextResolver[]  $resolvers
     */
    public function __construct(
        private array $resolvers,
    ) {}

    public function resolve(Request $request): mixed
    {
        foreach ($this->resolvers as $resolver) {
            $result = $resolver->resolve($request);

            if ($result !== null) {
                return $result;
            }
        }

        return null;
    }
}
