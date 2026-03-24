<?php

namespace GaiaTools\ContentAccord\Commands;

use GaiaTools\ContentAccord\Routing\RouteVersionMetadata;
use GaiaTools\ContentAccord\ValueObjects\ApiVersion;
use Illuminate\Foundation\Console\RouteListCommand;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Illuminate\Support\Stringable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'api:list')]
class ListApiVersionsCommand extends RouteListCommand
{
    protected $name = 'api:list';

    protected $description = 'List routes that belong to a registered API version.';

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

    /** @return array<int, mixed> */
    protected function getOptions(): array
    {
        return array_merge(parent::getOptions(), [
            ['summary', null, InputOption::VALUE_NONE, 'Show a version summary table instead of individual routes'],
            ['all', null, InputOption::VALUE_NONE, 'Include routes that have no API version assigned'],
        ]);
    }

    /**
     * @return array{domain: string|null, method: string, uri: string, name: string|null, action: string|null, middleware: string|null, path: string|null, vendor: bool, version: string|null}|null
     */
    protected function getRouteInformation(Route $route): ?array
    {
        $info = parent::getRouteInformation($route);

        $config = config()->array('content-accord.versioning', []);
        $metadata = RouteVersionMetadata::resolve($route, $config);
        $versionString = $metadata['version'] ?? null;

        $version = is_string($versionString) && $versionString !== ''
            ? $this->formatVersion($versionString)
            : null;

        if (! $this->option('all') && $version === null) {
            return null;
        }

        $domain = $info['domain'] ?? null;
        $method = $info['method'] ?? null;
        $uri = $info['uri'] ?? null;
        $name = $info['name'] ?? null;
        $action = $info['action'] ?? null;
        $middleware = $info['middleware'] ?? null;
        $path = $info['path'] ?? null;
        $vendor = $info['vendor'] ?? false;

        return [
            'domain' => is_string($domain) ? $domain : null,
            'method' => is_string($method) ? $method : '',
            'uri' => is_string($uri) ? $uri : '',
            'name' => is_string($name) ? $name : null,
            'action' => is_string($action) ? $action : null,
            'middleware' => is_string($middleware) ? $middleware : null,
            'path' => is_string($path) ? $path : null,
            'vendor' => (bool) $vendor,
            'version' => $version,
        ];
    }

    /**
     * @param  Collection<int, array{domain: string|null, method: string, uri: string, name: string|null, action: string|null, middleware: string|null, path: string|null, vendor: bool, version: string|null}>  $routes
     * @return array<int, mixed>
     */
    protected function forCli($routes): array
    {
        $routes = $routes->map(
            fn (array $route) => array_merge($route, [
                'action' => $this->formatActionForCli($route),
                'method' => $route['method'] == 'GET|HEAD|POST|PUT|PATCH|DELETE|OPTIONS' ? 'ANY' : $route['method'],
                'uri' => $route['domain'] ? ($route['domain'].'/'.ltrim($route['uri'], '/')) : $route['uri'],
            ]),
        );

        $maxMethod = $routes->reduce(fn (int $carry, array $route) => max($carry, mb_strlen($route['method'])), 0);
        $maxVersion = $routes->reduce(fn (int $carry, array $route) => max($carry, mb_strlen($route['version'] ?? '')), 0);

        $terminalWidth = $this->getTerminalWidth();
        $routeCount = $this->determineRouteCountOutput($routes, $terminalWidth);

        return $routes->map(fn (array $route) => $this->formatRouteForCli($route, $maxMethod, $maxVersion, $terminalWidth))
            ->flatten()
            ->filter()
            ->prepend('')
            ->push('')->push($routeCount)->push('')
            ->toArray();
    }

    /**
     * @param  array{action: string|null, method: string, middleware: string|null, uri: string, version: string|null}  $route
     * @return array{0: string, 1: string|null}
     */
    private function formatRouteForCli(array $route, int $maxMethod, int $maxVersion, int $terminalWidth): array
    {
        ['action' => $action, 'method' => $method, 'middleware' => $middleware, 'uri' => $uri, 'version' => $version] = $route;

        $paddedVersion = str_pad($version ?? '', $maxVersion);

        // When a version column is present, shift middleware indent right by maxVersion + 2
        $versionIndent = $maxVersion > 0 ? $maxVersion + 2 : 0;
        $middleware = $this->formatMiddlewareLines($middleware ?? '', $maxMethod, $versionIndent);

        $spaces = str_repeat(' ', max($maxMethod + 6 - mb_strlen($method), 0));

        // Version column: paddedVersion + 2-space separator (only when any route has a version)
        $versionColumn = $maxVersion > 0 ? $paddedVersion.'  ' : '';

        $dots = $this->calculateDots($method, $spaces, $versionColumn, $uri, $action, $terminalWidth);
        $action = $this->maybeTruncateAction($action, $method, $spaces, $versionColumn, $uri, $dots, $terminalWidth);

        $versionFormatted = $maxVersion > 0 ? sprintf('<fg=#6C7280>%s</>  ', $paddedVersion) : '';

        return $this->buildRouteRow($method, $spaces, $versionFormatted, $uri, $dots, $action, $middleware);
    }

    /** @return array{0: string, 1: string|null} */
    private function buildRouteRow(string $method, string $spaces, string $versionFormatted, string $uri, string $dots, ?string $action, string $middleware): array
    {
        return [sprintf(
            '  <fg=white;options=bold>%s</> %s%s<fg=white>%s</><fg=#6C7280>%s %s</>',
            $this->colorizeMethod($method),
            $spaces,
            $versionFormatted,
            preg_replace('#({[^}]+})#', '<fg=yellow>$1</>', $uri),
            $dots,
            str_replace('   ', ' › ', $action ?? ''),
        ), $this->output->isVerbose() && ! empty($middleware) ? "<fg=#6C7280>$middleware</>" : null];
    }

    private function calculateDots(string $method, string $spaces, string $versionColumn, string $uri, ?string $action, int $terminalWidth): string
    {
        $dots = str_repeat('.', max(
            $terminalWidth - mb_strlen($method.$spaces.$versionColumn.$uri.($action ?? '')) - 6 - ($action ? 1 : 0),
            0,
        ));

        return empty($dots) ? $dots : " $dots";
    }

    private function maybeTruncateAction(?string $action, string $method, string $spaces, string $versionColumn, string $uri, string $dots, int $terminalWidth): ?string
    {
        if ($action && ! $this->output->isVerbose() && mb_strlen($method.$spaces.$versionColumn.$uri.$action.$dots) > ($terminalWidth - 6)) {
            return substr($action, 0, $terminalWidth - 7 - mb_strlen($method.$spaces.$versionColumn.$uri.$dots)).'…';
        }

        return $action;
    }

    private function formatMiddlewareLines(string $middleware, int $maxMethod, int $versionIndent): string
    {
        return (new Stringable($middleware))->explode("\n")->filter()->whenNotEmpty(
            fn ($collection) => $collection->map(
                fn ($m) => sprintf('%s⇂ %s', str_repeat(' ', 9 + $maxMethod + $versionIndent), $m)
            )
        )->implode("\n");
    }

    private function colorizeMethod(string $method): string
    {
        return (new Stringable($method))->explode('|')->map(
            fn ($m) => sprintf('<fg=%s>%s</>', $this->verbColors[$m] ?? 'default', $m),
        )->implode('<fg=#6C7280>|</>');
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
            try {
                $majorKey = ApiVersion::parse((string) $version)->major;
                $count = $routeCounts[$majorKey] ?? 0;
            } catch (\Throwable) {
                $count = 0;
            }

            $rows[] = [
                $version,
                ($metadata['deprecated'] ?? false) ? 'yes' : 'no',
                $metadata['sunset'] ?? '-',
                $metadata['deprecation_link'] ?? '-',
                (string) $count,
            ];
        }

        $table = new Table($this->output);
        $table->setHeaders(['Version', 'Deprecated', 'Sunset', 'Deprecation Link', 'Routes']);
        $table->setRows($rows);
        $table->render();
    }

    /**
     * @return array<int, int>
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
                $major = $version->major;
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
