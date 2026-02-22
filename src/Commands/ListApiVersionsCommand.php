<?php

namespace GaiaTools\ContentAccord\Commands;

use GaiaTools\ContentAccord\Routing\RouteVersionMetadata;
use GaiaTools\ContentAccord\ValueObjects\ApiVersion;
use Illuminate\Console\Command;
use Illuminate\Routing\Router;
use Symfony\Component\Console\Helper\Table;

class ListApiVersionsCommand extends Command
{
    protected $signature = 'api:versions';

    protected $description = 'List registered API versions and their deprecation metadata.';

    public function handle(Router $router): int
    {
        $versions = config('content-accord.versioning.versions', []);

        if ($versions === []) {
            $this->info('No API versions configured.');

            return self::SUCCESS;
        }

        $routeCounts = $this->countRoutesByVersion($router);

        $rows = [];
        foreach ($versions as $version => $metadata) {
            $rows[] = [
                $version,
                ($metadata['deprecated'] ?? false) ? 'yes' : 'no',
                $metadata['sunset'] ?? '-',
                $metadata['deprecation_link'] ?? '-',
                (string) ($routeCounts[(string) $version] ?? 0),
            ];
        }

        $table = new Table($this->output);
        $table->setHeaders(['Version', 'Deprecated', 'Sunset', 'Deprecation Link', 'Routes']);
        $table->setRows($rows);
        $table->render();

        return self::SUCCESS;
    }

    /**
     * @return array<string, int>
     */
    private function countRoutesByVersion(Router $router): array
    {
        $counts = [];

        foreach ($router->getRoutes()->getRoutes() as $route) {
            $metadata = RouteVersionMetadata::resolve($route, config('content-accord.versioning', []));
            $versionString = $metadata['version'] ?? null;

            if (! is_string($versionString) || $versionString === '') {
                continue;
            }

            $version = ApiVersion::parse($versionString);
            $major = (string) $version->major;

            $counts[$major] = ($counts[$major] ?? 0) + 1;
        }

        return $counts;
    }
}
