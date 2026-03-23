<?php

namespace GaiaTools\ContentAccord\Commands;

use GaiaTools\ContentAccord\Routing\RouteVersionMetadata;
use GaiaTools\ContentAccord\ValueObjects\ApiVersion;
use Illuminate\Foundation\Console\RouteListCommand;
use Illuminate\Routing\Route;
use Illuminate\Support\Stringable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'api:list')]
class ListApiVersionsCommand extends RouteListCommand
{
    protected $name = 'api:list';

    protected $description = 'List registered API versions and their routes.';

    /** @var string[] */
    protected $headers = ['Domain', 'Method', 'URI', 'Name', 'Action', 'Middleware', 'Path', 'Version'];

    public function handle(): void
    {
        if ($this->option('summary')) {
            $this->showSummary();

            return;
        }

        parent::handle();
    }

    protected function getOptions(): array
    {
        return array_merge(parent::getOptions(), [
            ['summary', null, InputOption::VALUE_NONE, 'Show a version summary table instead of individual routes'],
            ['versioned', null, InputOption::VALUE_NONE, 'Only show routes that have an API version assigned'],
        ]);
    }

    protected function getRouteInformation(Route $route): ?array
    {
        $info = parent::getRouteInformation($route);

        if ($info === null) {
            return null;
        }

        $config = config()->array('content-accord.versioning', []);
        $metadata = RouteVersionMetadata::resolve($route, $config);
        $versionString = $metadata['version'] ?? null;

        $info['version'] = is_string($versionString) && $versionString !== ''
            ? $this->formatVersion($versionString)
            : null;

        if ($this->option('versioned') && $info['version'] === null) {
            return null;
        }

        return $info;
    }

    protected function forCli($routes): array
    {
        $routes = $routes->map(
            fn ($route) => array_merge($route, [
                'action' => $this->formatActionForCli($route),
                'method' => $route['method'] == 'GET|HEAD|POST|PUT|PATCH|DELETE|OPTIONS' ? 'ANY' : $route['method'],
                'uri' => $route['domain'] ? ($route['domain'].'/'.ltrim($route['uri'], '/')) : $route['uri'],
            ]),
        );

        $maxMethod = mb_strlen($routes->max('method'));
        $maxVersion = (int) mb_strlen($routes->max('version') ?? '');

        $terminalWidth = $this->getTerminalWidth();
        $routeCount = $this->determineRouteCountOutput($routes, $terminalWidth);

        return $routes->map(function ($route) use ($maxMethod, $maxVersion, $terminalWidth) {
            [
                'action' => $action,
                'method' => $method,
                'middleware' => $middleware,
                'uri' => $uri,
                'version' => $version,
            ] = $route;

            $paddedVersion = str_pad((string) ($version ?? ''), $maxVersion);

            // When a version column is present, shift middleware indent right by maxVersion + 2
            $versionIndent = $maxVersion > 0 ? $maxVersion + 2 : 0;
            $middleware = (new Stringable($middleware))->explode("\n")->filter()->whenNotEmpty(
                fn ($collection) => $collection->map(
                    fn ($middleware) => sprintf(
                        '%s⇂ %s',
                        str_repeat(' ', 9 + $maxMethod + $versionIndent),
                        $middleware,
                    )
                )
            )->implode("\n");

            $spaces = str_repeat(' ', max($maxMethod + 6 - mb_strlen($method), 0));

            // Version column: paddedVersion + 2-space separator (only when any route has a version)
            $versionColumn = $maxVersion > 0 ? $paddedVersion.'  ' : '';

            $dots = str_repeat('.', max(
                $terminalWidth - mb_strlen($method.$spaces.$versionColumn.$uri.$action) - 6 - ($action ? 1 : 0),
                0,
            ));

            $dots = empty($dots) ? $dots : " $dots";

            if ($action && ! $this->output->isVerbose() && mb_strlen($method.$spaces.$versionColumn.$uri.$action.$dots) > ($terminalWidth - 6)) {
                $action = substr($action, 0, $terminalWidth - 7 - mb_strlen($method.$spaces.$versionColumn.$uri.$dots)).'…';
            }

            $method = (new Stringable($method))->explode('|')->map(
                fn ($m) => sprintf('<fg=%s>%s</>', $this->verbColors[$m] ?? 'default', $m),
            )->implode('<fg=#6C7280>|</>');

            $versionFormatted = $maxVersion > 0
                ? sprintf('<fg=#6C7280>%s</>  ', $paddedVersion)
                : '';

            return [sprintf(
                '  <fg=white;options=bold>%s</> %s%s<fg=white>%s</><fg=#6C7280>%s %s</>',
                $method,
                $spaces,
                $versionFormatted,
                preg_replace('#({[^}]+})#', '<fg=yellow>$1</>', $uri),
                $dots,
                str_replace('   ', ' › ', $action ?? ''),
            ), $this->output->isVerbose() && ! empty($middleware) ? "<fg=#6C7280>$middleware</>" : null];
        })
            ->flatten()
            ->filter()
            ->prepend('')
            ->push('')->push($routeCount)->push('')
            ->toArray();
    }

    private function showSummary(): void
    {
        $versions = config()->array('content-accord.versioning.versions', []);
        $versions = array_filter($versions, static fn ($metadata) => is_array($metadata));

        if ($versions === []) {
            $this->info('No API versions configured.');

            return;
        }

        $routeCounts = $this->countRoutesByVersion();

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
    }

    /**
     * @return array<string, int>
     */
    private function countRoutesByVersion(): array
    {
        $counts = [];

        foreach ($this->router->getRoutes()->getRoutes() as $route) {
            $metadata = RouteVersionMetadata::resolve($route, config()->array('content-accord.versioning', []));
            $versionString = $metadata['version'] ?? null;

            if (! is_string($versionString) || $versionString === '') {
                continue;
            }

            try {
                $version = ApiVersion::parse($versionString);
                $major = (string) $version->major;
                $counts[$major] = ($counts[$major] ?? 0) + 1;
            } catch (\Throwable) {
                continue;
            }
        }

        return $counts;
    }

    private function formatVersion(string $versionString): ?string
    {
        try {
            $parsed = ApiVersion::parse($versionString);

            return 'v'.$parsed->major;
        } catch (\Throwable) {
            return null;
        }
    }
}
