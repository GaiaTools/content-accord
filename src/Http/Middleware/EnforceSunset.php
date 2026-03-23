<?php

namespace GaiaTools\ContentAccord\Http\Middleware;

use Closure;
use DateTime;
use GaiaTools\ContentAccord\Routing\RouteVersionMetadata;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Returns 410 Gone for any route whose sunset date has passed.
 *
 * Attach this middleware to versioned route groups (or globally) alongside
 * DeprecationHeaders. While DeprecationHeaders informs clients of the
 * upcoming shutdown via response headers, EnforceSunset actively blocks
 * requests once the shutdown date has been reached.
 */
final readonly class EnforceSunset
{
    public function handle(Request $request, Closure $next): mixed
    {
        $route = $request->route();
        $sunsetDate = $route ? $this->parseSunsetDate($route) : null;

        if ($sunsetDate !== null && new DateTime > $sunsetDate) {
            return response()->json([
                'message' => 'This API version has been sunset and is no longer available.',
                'sunset' => $sunsetDate->format('Y-m-d'),
            ], Response::HTTP_GONE);
        }

        return $next($request);
    }

    private function parseSunsetDate(Route $route): ?DateTime
    {
        $metadata = RouteVersionMetadata::resolve($route, config()->array('content-accord.versioning', []));
        $sunset = $metadata['sunset'] ?? null;

        if (! is_string($sunset) || $sunset === '') {
            return null;
        }

        try {
            return new DateTime($sunset);
        } catch (Throwable) {
            return null;
        }
    }
}
