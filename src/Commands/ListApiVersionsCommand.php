<?php

namespace GaiaTools\ContentAccord\Commands;

use GaiaTools\ContentAccord\Routing\RouteVersionMetadata;
use GaiaTools\ContentAccord\ValueObjects\ApiVersion;
use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Symfony\Component\Console\Helper\Table;

class ListApiVersionsCommand extends Command
{
    protected $signature = 'api:versions {--routes : Show individual routes for each version}';

    protected $description = 'List registered API versions and their deprecation metadata.';

    public function handle(Router $router): int
    {
        $versions = config()->array('content-accord.versioning.versions', []);
        $versions = array_filter($versions, static fn ($metadata) => is_array($metadata));
        /** @var array<string, array<string, mixed>> $versions */
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

        if ($this->option('routes')) {
            $this->listRoutesByVersion($router, $versions);
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, array<string, mixed>>  $versions
     */
    private function listRoutesByVersion(Router $router, array $versions): void
    {
        $config = config()->array('content-accord.versioning', []);

        foreach (array_keys($versions) as $version) {
            $rows = array_filter(array_map(
                fn ($route) => $this->buildRouteRow($route, (string) $version, $config),
                $router->getRoutes()->getRoutes(),
            ));

            if ($rows === []) {
                continue;
            }

            $this->newLine();
            $this->comment("Version {$version} routes:");
            $table = new Table($this->output);
            $table->setHeaders(['Method', 'URI', 'Action']);
            $table->setRows(array_values($rows));
            $table->render();
        }
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<int, string>|null
     */
    private function buildRouteRow(Route $route, string $version, array $config): ?array
    {
        $metadata = RouteVersionMetadata::resolve($route, $config);
        $parsed = $this->parseRouteVersion($metadata['version'] ?? null);

        if ($parsed === null || (string) $parsed->major !== $version) {
            return null;
        }

        $action = $route->getAction('controller') ?? $route->getAction('uses');

        return [
            implode('|', $route->methods()),
            $route->uri(),
            is_string($action) ? $action : 'Closure',
        ];
    }

    private function parseRouteVersion(mixed $versionString): ?ApiVersion
    {
        if (! is_string($versionString) || $versionString === '') {
            return null;
        }

        try {
            return ApiVersion::parse($versionString);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, int>
     */
    private function countRoutesByVersion(Router $router): array
    {
        /** @var array<string, int> $counts */
        $counts = [];

        foreach ($router->getRoutes()->getRoutes() as $route) {
            $metadata = RouteVersionMetadata::resolve($route, config()->array('content-accord.versioning', []));
            $versionString = $metadata['version'] ?? null;

            if (! is_string($versionString) || $versionString === '') {
                continue;
            }

            $version = ApiVersion::parse($versionString);
            $major = (string) $version->major;

            $counts[$major] = ($counts[$major] ?? 0) + 1;
        }

        $normalized = [];
        foreach ($counts as $key => $value) {
            $normalized[(string) $key] = $value;
        }

        return $normalized;
    }
}
