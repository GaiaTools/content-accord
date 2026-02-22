<?php

namespace GaiaTools\ContentAccord\Http\Middleware;

use Closure;
use DateTime;
use GaiaTools\ContentAccord\Routing\RouteVersionMetadata;
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

        $metadata = RouteVersionMetadata::resolve($route, config()->array('content-accord.versioning', []));
        $deprecated = $metadata['deprecated'] ?? false;

        if (! $deprecated) {
            return $response;
        }

        // Add Deprecation header
        $response->headers->set('Deprecation', 'true');

        // Add Sunset header if configured
        if (isset($metadata['sunset'])) {
            $sunsetDate = $this->formatSunsetDate($metadata['sunset']);
            $response->headers->set('Sunset', $sunsetDate);
        }

        // Add Link header if deprecation documentation is available
        if (isset($metadata['deprecation_link'])) {
            $link = "<{$metadata['deprecation_link']}>; rel=\"deprecation\"";
            $response->headers->set('Link', $link);
        }

        return $response;
    }

    private function formatSunsetDate(string $date): string
    {
        // Convert to RFC 7231 format (HTTP date)
        $dateTime = new DateTime($date);

        return $dateTime->format('D, d M Y H:i:s').' GMT';
    }
}
