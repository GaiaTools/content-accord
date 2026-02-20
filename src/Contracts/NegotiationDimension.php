<?php

namespace GaiaTools\ContentAccord\Contracts;

use Illuminate\Http\Request;

interface NegotiationDimension
{
    /**
     * A unique key identifying this dimension (e.g., 'version', 'locale', 'format').
     * Used as the storage key in NegotiatedContext.
     */
    public function key(): string;

    /**
     * The resolver (or chain of resolvers) for this dimension.
     */
    public function resolver(): ContextResolver;

    /**
     * Validate the resolved value.
     * Returns true if the value is acceptable for the current request.
     */
    public function validate(mixed $resolved, Request $request): bool;

    /**
     * Handle the case where no value could be resolved.
     * Should return a fallback value or throw an exception.
     */
    public function fallback(Request $request): mixed;
}
