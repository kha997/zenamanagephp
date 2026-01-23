<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class AuditRoutesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'routes:audit {--format=md : Output format (md|csv)} {--output=storage/app/routes-audit.md : Output file path}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Audit existing routes to find duplicates, overrides, and legacy stubs.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $format = Str::lower($this->option('format') ?? 'md');
        if (!in_array($format, ['md', 'csv'], true)) {
            $this->error('Format must be either "md" or "csv".');
            return self::FAILURE;
        }

        $outputTemplate = $this->option('output') ?? 'storage/app/routes-audit.(md|csv)';
        $outputPath = $this->resolveOutputPath($outputTemplate, $format);
        File::ensureDirectoryExists(dirname($outputPath));

        $routes = Route::getRoutes();
        $rows = [];
        $surfaceMap = [];
        $nameMap = [];
        $uriMiddlewareMap = [];
        $stubCandidates = [];

        foreach ($routes as $route) {
            $methods = array_filter($route->methods(), fn ($method) => $method !== 'HEAD');
            if (empty($methods)) {
                continue;
            }

            $uri = $route->uri();
            $middleware = $route->gatherMiddleware();
            $middlewareLabel = implode(', ', $middleware);
            $name = $route->getName() ?? '';
            $action = $route->getActionName();
            $group = $this->classifyGroup($uri);

            foreach ($methods as $method) {
                $surfaceKey = "{$method} {$uri}";
                $row = [
                    'method' => $method,
                    'uri' => $uri,
                    'name' => $name,
                    'action' => $action,
                    'middleware' => $middleware,
                    'middleware_label' => $middlewareLabel,
                    'group' => $group,
                    'surface_key' => $surfaceKey,
                ];

                $rows[] = $row;
                $surfaceMap[$surfaceKey][] = $row;

                if ($name !== '') {
                    $nameMap[$name][] = $row;
                }

                $uriMiddlewareMap[$uri][$middlewareLabel][] = $row;

                if ($this->isStubCandidate($action)) {
                    $stubCandidates[] = $row;
                }
            }
        }

        $duplicates = array_filter($surfaceMap, fn ($entries) => count($entries) > 1);
        $nameReuses = array_filter($nameMap, fn ($entries) => count($entries) > 1);
        $middlewareConflicts = array_filter($uriMiddlewareMap, fn ($variants) => count($variants) > 1);

        $content = $format === 'csv'
            ? $this->renderCsv($rows, $surfaceMap)
            : $this->renderMarkdown($rows, $duplicates, $nameReuses, $middlewareConflicts, $stubCandidates);

        File::put($outputPath, $content);
        $this->info("Route audit written to {$outputPath}");

        return self::SUCCESS;
    }

    /**
     * Convert the output template to a concrete path.
     */
    protected function resolveOutputPath(string $template, string $format): string
    {
        if (Str::contains($template, '(md|csv)')) {
            return str_replace('(md|csv)', $format, $template);
        }

        $extension = pathinfo($template, PATHINFO_EXTENSION);
        if ($extension === '') {
            return "{$template}.{$format}";
        }

        if ($extension !== $format) {
            return preg_replace('/\.[^.]+$/', ".{$format}", $template);
        }

        return $template;
    }

    /**
     * Classify routes into surface buckets.
     */
    protected function classifyGroup(string $uri): string
    {
        if (Str::startsWith($uri, 'admin/')) {
            return 'admin';
        }

        if (Str::startsWith($uri, 'api/v1/')) {
            return 'api_v1';
        }

        if (Str::startsWith($uri, 'api/zena/')) {
            return 'api_zena';
        }

        if (Str::startsWith($uri, 'api/')) {
            return 'api';
        }

        return 'web';
    }

    /**
     * Detect actions that look like inspection stubs.
     */
    protected function isStubCandidate(string $action): bool
    {
        return Str::contains($action, 'InspectionController');
    }

    /**
     * Render the Markdown report.
     */
    protected function renderMarkdown(array $rows, array $duplicates, array $nameReuses, array $middlewareConflicts, array $stubCandidates): string
    {
        $totalRoutes = count($rows);
        $duplicateGroups = count($duplicates);
        $nameReuseGroups = count($nameReuses);
        $middlewareConflictGroups = count($middlewareConflicts);
        $stubCount = count($stubCandidates);

        $content = "# Routes Audit\n\n";
        $content .= "## Summary\n";
        $content .= "- Total routes scanned: {$totalRoutes}\n";
        $content .= "- Duplicate surfaces detected: {$duplicateGroups}\n";
        $content .= "- Named routes reused: {$nameReuseGroups}\n";
        $content .= "- URIs with middleware variations: {$middlewareConflictGroups}\n";
        $content .= "- Stub candidates identified: {$stubCount}\n\n";

        $content .= "## Duplicates\n";
        if ($duplicateGroups === 0) {
            $content .= "_None detected._\n\n";
        } else {
            $content .= "| Method | URI | Actions |\n";
            $content .= "|---|---|---|\n";
            foreach ($duplicates as $rowsGroup) {
                $method = $rowsGroup[0]['method'];
                $uri = $rowsGroup[0]['uri'];
                $actions = array_unique(array_map(fn ($row) => $row['action'], $rowsGroup));
                $content .= sprintf(
                    "| %s | %s | %s |\n",
                    $method,
                    $uri,
                    implode('<br>', $actions)
                );
            }
            $content .= "\n";
        }

        $content .= "## Overrides\n\n";
        $content .= "### Named Route Reuse\n";
        if ($nameReuseGroups === 0) {
            $content .= "_None detected._\n\n";
        } else {
            $content .= "| Name | Count | Routes |\n";
            $content .= "|---|---|---|\n";
            foreach ($nameReuses as $name => $entries) {
                $routesList = array_map(fn ($row) => "{$row['method']} {$row['uri']} → {$row['action']}", $entries);
                $content .= sprintf(
                    "| %s | %d | %s |\n",
                    $name,
                    count($entries),
                    implode('<br>', array_unique($routesList))
                );
            }
            $content .= "\n";
        }

        $content .= "### Middleware Variations\n";
        if ($middlewareConflictGroups === 0) {
            $content .= "_None detected._\n\n";
        } else {
            $content .= "| URI | Middleware Stacks |\n";
            $content .= "|---|---|\n";
            foreach ($middlewareConflicts as $uri => $variants) {
                $stacks = [];
                foreach ($variants as $stack => $entries) {
                    $label = $stack === '' ? '[none]' : $stack;
                $stacks[] = "{$label} (" . count($entries) . " routes)";
                }
                $content .= sprintf(
                    "| %s | %s |\n",
                    $uri,
                    implode('<br>', $stacks)
                );
            }
            $content .= "\n";
        }

        $content .= "## Stub Candidates\n";
        if ($stubCount === 0) {
            $content .= "_None detected._\n\n";
        } else {
            $content .= "| Method | URI | Action | Middleware | Group |\n";
            $content .= "|---|---|---|---|---|\n";
            foreach ($stubCandidates as $row) {
                $content .= sprintf(
                    "| %s | %s | %s | %s | %s |\n",
                    $row['method'],
                    $row['uri'],
                    $row['action'],
                    $row['middleware_label'] === '' ? '[none]' : $row['middleware_label'],
                    $row['group']
                );
            }
            $content .= "\n";
        }

        $content .= "## Full Inventory\n";
        $content .= "| Method | URI | Name | Action | Middleware | Group |\n";
        $content .= "|---|---|---|---|---|---|\n";
        foreach ($rows as $row) {
            $content .= sprintf(
                "| %s | %s | %s | %s | %s | %s |\n",
                $row['method'],
                $row['uri'],
                $row['name'] ?: 'N/A',
                $row['action'],
                $row['middleware_label'] ?: '[none]',
                $row['group']
            );
        }

        return $content;
    }

    /**
     * Render the CSV report.
     */
    protected function renderCsv(array $rows, array $surfaceMap): string
    {
        $handle = fopen('php://temp', 'r+');
        $header = ['method', 'uri', 'name', 'action', 'middleware', 'group', 'duplicate_key', 'duplicate_count'];
        fputcsv($handle, $header);

        foreach ($rows as $row) {
            $surfaceKey = $row['surface_key'];
            $duplicateCount = isset($surfaceMap[$surfaceKey]) ? count($surfaceMap[$surfaceKey]) : 0;
            $duplicateKey = $duplicateCount > 1 ? $surfaceKey : '';
            $middleware = $row['middleware_label'] ?: '';

            fputcsv($handle, [
                $row['method'],
                $row['uri'],
                $row['name'],
                $row['action'],
                $middleware,
                $row['group'],
                $duplicateKey,
                $duplicateCount > 1 ? $duplicateCount : '',
            ]);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return $content;
    }
}
