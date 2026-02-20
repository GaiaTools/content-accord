<?php

namespace GaiaTools\ContentAccord\Http\Middleware;

use Closure;
use DateTime;
use Illuminate\Http\Request;

final readonly class DeprecationHeaders
{
    public function handle(Request $request, Closure $next): mixed
    {
        $response = $next($request);

        $route = $request->route();

        if (! $route) {
            return $response;
        }

        $action = $route->getAction();

        $deprecated = $action['deprecated'] ?? false;

        if (! $deprecated) {
            return $response;
        }

        // Add Deprecation header
        $response->headers->set('Deprecation', 'true');

        // Add Sunset header if configured
        if (isset($action['sunset'])) {
            $sunsetDate = $this->formatSunsetDate($action['sunset']);
            $response->headers->set('Sunset', $sunsetDate);
        }

        // Add Link header if deprecation documentation is available
        if (isset($action['deprecation_link'])) {
            $link = "<{$action['deprecation_link']}>; rel=\"deprecation\"";
            $response->headers->set('Link', $link);
        }

        return $response;
    }

    private function formatSunsetDate(string $date): string
    {
        // Convert to RFC 7231 format (HTTP date)
        $dateTime = new DateTime($date);

        return $dateTime->format('D, d M Y H:i:s') . ' GMT';
    }
}
