<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateOpenApiSpec extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'openapi:generate 
                            {--copy-to-docs : Copy generated spec to docs/api/openapi.yaml}
                            {--format=json : Output format (json or yaml)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate OpenAPI specification from code annotations and optionally copy to docs/api/openapi.yaml';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Generating OpenAPI specification...');

        // Run l5-swagger generate command
        $this->call('l5-swagger:generate');

        $format = $this->option('format');
        $copyToDocs = $this->option('copy-to-docs');

        // Determine source file based on format
        if ($format === 'yaml') {
            $sourceFile = storage_path('api-docs/api-docs.yaml');
            $targetFile = base_path('docs/api/openapi.yaml');
        } else {
            $sourceFile = storage_path('api-docs/api-docs.json');
            $targetFile = base_path('docs/api/openapi.json');
        }

        // Check if source file exists
        if (!File::exists($sourceFile)) {
            $this->error("Generated OpenAPI spec not found at: {$sourceFile}");
            $this->info('Run: php artisan l5-swagger:generate first');
            return Command::FAILURE;
        }

        $this->info("OpenAPI spec generated at: {$sourceFile}");

        // Inject ability matrix into OpenAPI spec
        $this->injectAbilityMatrix($sourceFile, $format);

        // Copy to docs folder if requested
        if ($copyToDocs) {
            $docsDir = base_path('docs/api');
            
            // Ensure docs/api directory exists
            if (!File::isDirectory($docsDir)) {
                File::makeDirectory($docsDir, 0755, true);
            }

            // Copy file
            File::copy($sourceFile, $targetFile);
            $this->info("Copied OpenAPI spec to: {$targetFile}");

            // If YAML, also update JSON version for frontend
            if ($format === 'yaml') {
                $jsonSource = storage_path('api-docs/api-docs.json');
                $jsonTarget = base_path('docs/api/openapi.json');
                if (File::exists($jsonSource)) {
                    File::copy($jsonSource, $jsonTarget);
                    $this->info("Also copied JSON version to: {$jsonTarget}");
                }
            }
        }

        $this->info('OpenAPI specification generation completed successfully!');
        $this->newLine();
        $this->info('Next steps:');
        $this->line('1. Review the generated spec at: ' . $sourceFile);
        if ($copyToDocs) {
            $this->line('2. Spec copied to docs folder for version control');
        } else {
            $this->line('2. Run with --copy-to-docs to copy to docs/api/');
        }
        $this->line('3. Run frontend codegen: cd frontend && pnpm generate:api-types');

        return Command::SUCCESS;
    }

    /**
     * Inject ability matrix into OpenAPI spec
     * Adds x-abilities extension to each endpoint based on route definitions
     */
    private function injectAbilityMatrix(string $specFile, string $format): void
    {
        try {
            $abilityService = app(\App\Services\AbilityMatrixService::class);
            $abilityMatrix = $abilityService->exportForOpenAPI();

            // Read existing spec
            $specContent = File::get($specFile);
            
            if ($format === 'json') {
                $spec = json_decode($specContent, true);
            } else {
                // For YAML, try to parse with Symfony YAML if available
                if (class_exists(\Symfony\Component\Yaml\Yaml::class)) {
                    $spec = \Symfony\Component\Yaml\Yaml::parse($specContent);
                } else {
                    // Fallback: convert YAML to JSON for processing
                    $this->warn('YAML parser not available. Install symfony/yaml for full YAML support.');
                    // Try to parse as JSON if it's already JSON-like
                    $spec = json_decode($specContent, true);
                    if (!$spec) {
                        $this->warn('Could not parse OpenAPI spec. Skipping ability matrix injection.');
                        return;
                    }
                }
            }

            if (!$spec) {
                $this->warn('Could not parse OpenAPI spec for ability matrix injection');
                return;
            }

            // Inject x-abilities at top level (for reference)
            if (!isset($spec['x-abilities'])) {
                $spec['x-abilities'] = $abilityMatrix['x-abilities'] ?? [];
            }

            // Inject x-abilities for each endpoint based on HTTP method and path
            if (isset($spec['paths'])) {
                foreach ($spec['paths'] as $path => &$pathItem) {
                    foreach (['get', 'post', 'put', 'patch', 'delete'] as $method) {
                        if (isset($pathItem[$method])) {
                            // Determine abilities based on path and method
                            $abilities = $this->getAbilitiesForEndpoint($path, $method);
                            if (!empty($abilities)) {
                                $pathItem[$method]['x-abilities'] = $abilities;
                            }
                        }
                    }
                }
            }

            // Write back
            if ($format === 'json') {
                File::put($specFile, json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            } else {
                // Write as YAML if parser available
                if (class_exists(\Symfony\Component\Yaml\Yaml::class)) {
                    File::put($specFile, \Symfony\Component\Yaml\Yaml::dump($spec, 10, 2));
                } else {
                    // Fallback to JSON
                    File::put($specFile, json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                }
            }
            
            $this->info('Injected ability matrix into OpenAPI spec');
        } catch (\Exception $e) {
            $this->warn('Failed to inject ability matrix: ' . $e->getMessage());
        }
    }

    /**
     * Get abilities required for an endpoint based on path and method
     * 
     * @param string $path API path (e.g., /api/v1/app/projects)
     * @param string $method HTTP method (get, post, put, patch, delete)
     * @return array List of ability codes
     */
    private function getAbilitiesForEndpoint(string $path, string $method): array
    {
        $abilities = [];

        // Map paths to resource and actions
        if (preg_match('#/app/(projects|tasks|documents|users|templates|reports|change-requests|quotes)#', $path, $matches)) {
            $resource = $matches[1];
            
            // Normalize resource name (change-requests -> change_requests)
            $resource = str_replace('-', '_', $resource);
            
            // Map HTTP methods to actions
            $actionMap = [
                'get' => 'view',
                'post' => 'create',
                'put' => 'modify',
                'patch' => 'modify',
                'delete' => 'delete',
            ];
            
            $action = $actionMap[$method] ?? 'view';
            $abilities[] = "{$resource}.{$action}";
            
            // Special cases
            if ($method === 'get' && str_contains($path, '/kpis')) {
                $abilities[] = "{$resource}.view";
            }
            if (str_contains($path, '/approve')) {
                $abilities[] = "{$resource}.approve";
            }
        }

        // Admin endpoints
        if (str_contains($path, '/admin/') || str_contains($path, '/api/v1/admin/')) {
            $abilities[] = 'admin.access';
        }

        // Tenant-scoped endpoints
        if (str_contains($path, '/app/')) {
            $abilities[] = 'admin.access.tenant';
        }

        return array_unique($abilities);
    }
}
