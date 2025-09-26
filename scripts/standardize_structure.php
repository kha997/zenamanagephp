<?php

/**
 * Script to standardize project structure
 * Moves files from src/ to app/ following Laravel conventions
 */

class StructureStandardizer
{
    private $basePath;
    private $logFile;
    private $movedFiles = [];
    private $errors = [];

    public function __construct($basePath)
    {
        $this->basePath = $basePath;
        $this->logFile = $basePath . '/structure_standardization.log';
    }

    public function run()
    {
        echo "🚀 Starting structure standardization...\n";
        $this->log("Starting structure standardization at " . date('Y-m-d H:i:s'));

        try {
            // Step 1: Move Models
            $this->moveModels();
            
            // Step 2: Move Services
            $this->moveServices();
            
            // Step 3: Move Controllers
            $this->moveControllers();
            
            // Step 4: Move other classes
            $this->moveOtherClasses();
            
            // Step 5: Update namespaces
            $this->updateNamespaces();
            
            // Step 6: Clean up empty directories
            $this->cleanupEmptyDirectories();
            
            // Step 7: Generate report
            $this->generateReport();
            
        } catch (Exception $e) {
            $this->log("ERROR: " . $e->getMessage());
            echo "❌ Error: " . $e->getMessage() . "\n";
        }
    }

    private function moveModels()
    {
        echo "📁 Moving Models...\n";
        $srcModels = glob($this->basePath . '/src/*/Models/*.php');
        
        foreach ($srcModels as $model) {
            $filename = basename($model);
            $targetPath = $this->basePath . '/app/Models/' . $filename;
            
            // Check if target already exists
            if (file_exists($targetPath)) {
                $this->log("SKIP: Model $filename already exists in app/Models/");
                continue;
            }
            
            if (copy($model, $targetPath)) {
                $this->movedFiles[] = [
                    'type' => 'Model',
                    'from' => $model,
                    'to' => $targetPath,
                    'status' => 'moved'
                ];
                $this->log("MOVED: $model -> $targetPath");
                echo "  ✅ Moved: $filename\n";
            } else {
                $this->errors[] = "Failed to move $model";
                $this->log("ERROR: Failed to move $model");
            }
        }
    }

    private function moveServices()
    {
        echo "🔧 Moving Services...\n";
        $srcServices = glob($this->basePath . '/src/*/Services/*.php');
        
        foreach ($srcServices as $service) {
            $filename = basename($service);
            $targetPath = $this->basePath . '/app/Services/' . $filename;
            
            if (file_exists($targetPath)) {
                $this->log("SKIP: Service $filename already exists in app/Services/");
                continue;
            }
            
            if (copy($service, $targetPath)) {
                $this->movedFiles[] = [
                    'type' => 'Service',
                    'from' => $service,
                    'to' => $targetPath,
                    'status' => 'moved'
                ];
                $this->log("MOVED: $service -> $targetPath");
                echo "  ✅ Moved: $filename\n";
            } else {
                $this->errors[] = "Failed to move $service";
                $this->log("ERROR: Failed to move $service");
            }
        }
    }

    private function moveControllers()
    {
        echo "🎮 Moving Controllers...\n";
        $srcControllers = glob($this->basePath . '/src/*/Controllers/*.php');
        
        foreach ($srcControllers as $controller) {
            $filename = basename($controller);
            $targetPath = $this->basePath . '/app/Http/Controllers/' . $filename;
            
            if (file_exists($targetPath)) {
                $this->log("SKIP: Controller $filename already exists in app/Http/Controllers/");
                continue;
            }
            
            if (copy($controller, $targetPath)) {
                $this->movedFiles[] = [
                    'type' => 'Controller',
                    'from' => $controller,
                    'to' => $targetPath,
                    'status' => 'moved'
                ];
                $this->log("MOVED: $controller -> $targetPath");
                echo "  ✅ Moved: $filename\n";
            } else {
                $this->errors[] = "Failed to move $controller";
                $this->log("ERROR: Failed to move $controller");
            }
        }
    }

    private function moveOtherClasses()
    {
        echo "📦 Moving other classes...\n";
        
        // Move Requests
        $srcRequests = glob($this->basePath . '/src/*/Requests/*.php');
        foreach ($srcRequests as $request) {
            $filename = basename($request);
            $targetPath = $this->basePath . '/app/Http/Requests/' . $filename;
            
            if (!file_exists($targetPath)) {
                if (copy($request, $targetPath)) {
                    $this->movedFiles[] = [
                        'type' => 'Request',
                        'from' => $request,
                        'to' => $targetPath,
                        'status' => 'moved'
                    ];
                    echo "  ✅ Moved Request: $filename\n";
                }
            }
        }
        
        // Move Middleware
        $srcMiddleware = glob($this->basePath . '/src/*/Middleware/*.php');
        foreach ($srcMiddleware as $middleware) {
            $filename = basename($middleware);
            $targetPath = $this->basePath . '/app/Http/Middleware/' . $filename;
            
            if (!file_exists($targetPath)) {
                if (copy($middleware, $targetPath)) {
                    $this->movedFiles[] = [
                        'type' => 'Middleware',
                        'from' => $middleware,
                        'to' => $targetPath,
                        'status' => 'moved'
                    ];
                    echo "  ✅ Moved Middleware: $filename\n";
                }
            }
        }
    }

    private function updateNamespaces()
    {
        echo "🔄 Updating namespaces...\n";
        
        $filesToUpdate = array_merge(
            glob($this->basePath . '/app/Models/*.php'),
            glob($this->basePath . '/app/Services/*.php'),
            glob($this->basePath . '/app/Http/Controllers/*.php'),
            glob($this->basePath . '/app/Http/Requests/*.php'),
            glob($this->basePath . '/app/Http/Middleware/*.php')
        );
        
        foreach ($filesToUpdate as $file) {
            $content = file_get_contents($file);
            
            // Update namespace declarations
            $content = preg_replace('/namespace Src\\\\.*?;/', 'namespace App\\Models;', $content);
            $content = preg_replace('/namespace Src\\\\.*?;/', 'namespace App\\Services;', $content);
            $content = preg_replace('/namespace Src\\\\.*?;/', 'namespace App\\Http\\Controllers;', $content);
            $content = preg_replace('/namespace Src\\\\.*?;/', 'namespace App\\Http\\Requests;', $content);
            $content = preg_replace('/namespace Src\\\\.*?;/', 'namespace App\\Http\\Middleware;', $content);
            
            // Update use statements
            $content = preg_replace('/use Src\\\\.*?\\\\([^;]+);/', 'use App\\Models\\$1;', $content);
            $content = preg_replace('/use Src\\\\.*?\\\\([^;]+);/', 'use App\\Services\\$1;', $content);
            $content = preg_replace('/use Src\\\\.*?\\\\([^;]+);/', 'use App\\Http\\Controllers\\$1;', $content);
            
            file_put_contents($file, $content);
            echo "  🔄 Updated namespaces in: " . basename($file) . "\n";
        }
    }

    private function cleanupEmptyDirectories()
    {
        echo "🧹 Cleaning up empty directories...\n";
        
        $srcDirs = glob($this->basePath . '/src/*', GLOB_ONLYDIR);
        foreach ($srcDirs as $dir) {
            if ($this->isDirectoryEmpty($dir)) {
                rmdir($dir);
                echo "  🗑️ Removed empty directory: " . basename($dir) . "\n";
            }
        }
    }

    private function isDirectoryEmpty($dir)
    {
        return count(scandir($dir)) <= 2;
    }

    private function generateReport()
    {
        echo "📊 Generating report...\n";
        
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'moved_files' => $this->movedFiles,
            'errors' => $this->errors,
            'summary' => [
                'total_moved' => count($this->movedFiles),
                'total_errors' => count($this->errors),
                'models_moved' => count(array_filter($this->movedFiles, fn($f) => $f['type'] === 'Model')),
                'services_moved' => count(array_filter($this->movedFiles, fn($f) => $f['type'] === 'Service')),
                'controllers_moved' => count(array_filter($this->movedFiles, fn($f) => $f['type'] === 'Controller')),
            ]
        ];
        
        file_put_contents($this->basePath . '/structure_standardization_report.json', json_encode($report, JSON_PRETTY_PRINT));
        
        echo "✅ Standardization completed!\n";
        echo "📊 Summary:\n";
        echo "  - Files moved: " . $report['summary']['total_moved'] . "\n";
        echo "  - Models: " . $report['summary']['models_moved'] . "\n";
        echo "  - Services: " . $report['summary']['services_moved'] . "\n";
        echo "  - Controllers: " . $report['summary']['controllers_moved'] . "\n";
        echo "  - Errors: " . $report['summary']['total_errors'] . "\n";
        echo "📄 Report saved to: structure_standardization_report.json\n";
    }

    private function log($message)
    {
        file_put_contents($this->logFile, date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
    }
}

// Run the standardizer
$standardizer = new StructureStandardizer('/Applications/XAMPP/xamppfiles/htdocs/zenamanage');
$standardizer->run();
