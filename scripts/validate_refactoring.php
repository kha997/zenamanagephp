<?php

/**
 * Validation Script for Naming Convention Refactoring
 * 
 * This script validates that all references have been updated correctly
 * and identifies any remaining zena_ references.
 */

require_once __DIR__ . '/../vendor/autoload.php';

class RefactoringValidator
{
    private $mappings = [
        'zena_users' => 'users',
        'zena_components' => 'components',
        'zena_task_assignments' => 'task_assignments',
        'zena_documents' => 'documents',
        'zena_notifications' => 'notifications',
        'zena_roles' => 'roles',
        'zena_permissions' => 'permissions',
        'zena_role_permissions' => 'role_permissions',
        'zena_user_roles' => 'user_roles',
        'zena_audit_logs' => 'audit_logs',
        'zena_email_tracking' => 'email_tracking',
        'zena_system_settings' => 'system_settings',
        'zena_work_templates' => 'work_templates',
        'zena_template_tasks' => 'template_tasks',
        'zena_design_construction' => 'design_construction',
        'zena_change_requests' => 'change_requests',
        'zena_change_request_comments' => 'change_request_comments',
        'zena_change_request_approvals' => 'change_request_approvals',
    ];

    private $classMappings = [
        'ZenaUser' => 'User',
        'ZenaComponent' => 'Component',
        'ZenaTaskAssignment' => 'TaskAssignment',
        'ZenaDocument' => 'Document',
        'ZenaNotification' => 'Notification',
        'ZenaRole' => 'Role',
        'ZenaPermission' => 'Permission',
        'ZenaAuditLog' => 'AuditLog',
        'ZenaEmailTracking' => 'EmailTracking',
        'ZenaSystemSetting' => 'SystemSetting',
        'ZenaWorkTemplate' => 'WorkTemplate',
        'ZenaTemplateTask' => 'TemplateTask',
        'ZenaDesignConstruction' => 'DesignConstruction',
        'ZenaChangeRequest' => 'ChangeRequest',
        'ZenaChangeRequestComment' => 'ChangeRequestComment',
        'ZenaChangeRequestApproval' => 'ChangeRequestApproval',
    ];

    private $issues = [];
    private $warnings = [];

    public function validateAll()
    {
        echo "🔍 Starting comprehensive validation...\n\n";
        
        $this->validateDatabase();
        $this->validateModels();
        $this->validateControllers();
        $this->validateServices();
        $this->validateViews();
        $this->validateTests();
        $this->validateConfigs();
        $this->validateRoutes();
        $this->validateMigrations();
        
        $this->generateReport();
    }

    /**
     * Validate database tables
     */
    private function validateDatabase()
    {
        echo "📊 Validating database...\n";
        
        try {
            $tables = DB::select('SHOW TABLES');
            $tableNames = array_map('current', $tables);
            
            foreach ($this->mappings as $old => $new) {
                if (in_array($old, $tableNames)) {
                    $this->issues[] = "Database: Table '$old' still exists";
                }
                if (!in_array($new, $tableNames)) {
                    $this->issues[] = "Database: Table '$new' does not exist";
                }
            }
            
            echo "✅ Database validation completed\n";
            
        } catch (Exception $e) {
            $this->issues[] = "Database: Connection failed - " . $e->getMessage();
        }
    }

    /**
     * Validate Model classes
     */
    private function validateModels()
    {
        echo "🏗️ Validating Models...\n";
        
        $modelFiles = glob(__DIR__ . '/../app/Models/*.php');
        
        foreach ($modelFiles as $file) {
            $this->validateFile($file, 'Model');
        }
        
        // Check if models can be instantiated
        $models = ['User', 'Component', 'TaskAssignment', 'Document'];
        
        foreach ($models as $model) {
            try {
                $instance = new $model();
                if (!$instance instanceof \Illuminate\Database\Eloquent\Model) {
                    $this->issues[] = "Model: $model is not a valid Eloquent model";
                }
            } catch (Exception $e) {
                $this->warnings[] = "Model: $model instantiation failed - " . $e->getMessage();
            }
        }
        
        echo "✅ Models validation completed\n";
    }

    /**
     * Validate Controller classes
     */
    private function validateControllers()
    {
        echo "🎮 Validating Controllers...\n";
        
        $controllerFiles = glob(__DIR__ . '/../app/Http/Controllers/**/*.php');
        
        foreach ($controllerFiles as $file) {
            $this->validateFile($file, 'Controller');
        }
        
        echo "✅ Controllers validation completed\n";
    }

    /**
     * Validate Service classes
     */
    private function validateServices()
    {
        echo "⚙️ Validating Services...\n";
        
        $serviceFiles = glob(__DIR__ . '/../app/Services/*.php');
        
        foreach ($serviceFiles as $file) {
            $this->validateFile($file, 'Service');
        }
        
        echo "✅ Services validation completed\n";
    }

    /**
     * Validate View files
     */
    private function validateViews()
    {
        echo "🎨 Validating Views...\n";
        
        $viewFiles = glob(__DIR__ . '/../resources/views/**/*.blade.php');
        
        foreach ($viewFiles as $file) {
            $this->validateFile($file, 'View');
        }
        
        echo "✅ Views validation completed\n";
    }

    /**
     * Validate Test classes
     */
    private function validateTests()
    {
        echo "🧪 Validating Tests...\n";
        
        $testFiles = glob(__DIR__ . '/../tests/**/*.php');
        
        foreach ($testFiles as $file) {
            $this->validateFile($file, 'Test');
        }
        
        echo "✅ Tests validation completed\n";
    }

    /**
     * Validate Config files
     */
    private function validateConfigs()
    {
        echo "⚙️ Validating Configs...\n";
        
        $configFiles = glob(__DIR__ . '/../config/*.php');
        
        foreach ($configFiles as $file) {
            $this->validateFile($file, 'Config');
        }
        
        echo "✅ Configs validation completed\n";
    }

    /**
     * Validate Route files
     */
    private function validateRoutes()
    {
        echo "🛣️ Validating Routes...\n";
        
        $routeFiles = glob(__DIR__ . '/../routes/*.php');
        
        foreach ($routeFiles as $file) {
            $this->validateFile($file, 'Route');
        }
        
        echo "✅ Routes validation completed\n";
    }

    /**
     * Validate Migration files
     */
    private function validateMigrations()
    {
        echo "📦 Validating Migrations...\n";
        
        $migrationFiles = glob(__DIR__ . '/../database/migrations/*.php');
        
        foreach ($migrationFiles as $file) {
            $this->validateFile($file, 'Migration');
        }
        
        echo "✅ Migrations validation completed\n";
    }

    /**
     * Validate individual file
     */
    private function validateFile($filePath, $type)
    {
        if (!file_exists($filePath)) {
            return;
        }
        
        $content = file_get_contents($filePath);
        $fileName = basename($filePath);
        
        // Check for remaining zena_ references
        foreach ($this->mappings as $old => $new) {
            if (strpos($content, $old) !== false) {
                $this->issues[] = "$type: $fileName contains '$old' reference";
            }
        }
        
        // Check for remaining Zena class references
        foreach ($this->classMappings as $old => $new) {
            if (strpos($content, $old) !== false) {
                $this->issues[] = "$type: $fileName contains '$old' class reference";
            }
        }
        
        // Check for zena_ namespace references
        if (strpos($content, 'App\\Models\\Zena') !== false) {
            $this->issues[] = "$type: $fileName contains 'App\\Models\\Zena' namespace";
        }
        
        if (strpos($content, 'use App\\Models\\Zena') !== false) {
            $this->issues[] = "$type: $fileName contains 'use App\\Models\\Zena' import";
        }
        
        // Check for zena_ variable references
        if (strpos($content, '$zena') !== false) {
            $this->warnings[] = "$type: $fileName contains '\$zena' variable reference";
        }
    }

    /**
     * Generate validation report
     */
    private function generateReport()
    {
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "📋 VALIDATION REPORT\n";
        echo str_repeat("=", 60) . "\n\n";
        
        if (empty($this->issues) && empty($this->warnings)) {
            echo "🎉 SUCCESS: All validations passed!\n";
            echo "✅ No issues found\n";
            echo "✅ No warnings found\n";
            echo "✅ Refactoring appears to be complete\n\n";
        } else {
            if (!empty($this->issues)) {
                echo "❌ ISSUES FOUND (" . count($this->issues) . "):\n";
                foreach ($this->issues as $issue) {
                    echo "   • $issue\n";
                }
                echo "\n";
            }
            
            if (!empty($this->warnings)) {
                echo "⚠️ WARNINGS (" . count($this->warnings) . "):\n";
                foreach ($this->warnings as $warning) {
                    echo "   • $warning\n";
                }
                echo "\n";
            }
        }
        
        // Summary
        echo "📊 SUMMARY:\n";
        echo "   Issues: " . count($this->issues) . "\n";
        echo "   Warnings: " . count($this->warnings) . "\n";
        echo "   Status: " . (empty($this->issues) ? "✅ PASS" : "❌ FAIL") . "\n\n";
        
        // Recommendations
        if (!empty($this->issues)) {
            echo "🔧 RECOMMENDATIONS:\n";
            echo "   1. Fix all issues before proceeding\n";
            echo "   2. Re-run validation after fixes\n";
            echo "   3. Test functionality manually\n";
            echo "   4. Consider rollback if issues persist\n\n";
        }
        
        // Save report to file
        $reportFile = __DIR__ . '/../storage/logs/validation_report_' . date('Y-m-d_H-i-s') . '.txt';
        $reportContent = "Validation Report - " . date('Y-m-d H:i:s') . "\n";
        $reportContent .= str_repeat("=", 60) . "\n\n";
        
        if (!empty($this->issues)) {
            $reportContent .= "ISSUES:\n";
            foreach ($this->issues as $issue) {
                $reportContent .= "• $issue\n";
            }
            $reportContent .= "\n";
        }
        
        if (!empty($this->warnings)) {
            $reportContent .= "WARNINGS:\n";
            foreach ($this->warnings as $warning) {
                $reportContent .= "• $warning\n";
            }
            $reportContent .= "\n";
        }
        
        file_put_contents($reportFile, $reportContent);
        echo "📄 Report saved to: $reportFile\n";
    }

    /**
     * Search for specific patterns
     */
    public function searchPatterns()
    {
        echo "🔍 Searching for specific patterns...\n\n";
        
        $patterns = [
            'zena_users' => 'zena_users',
            'zena_components' => 'zena_components',
            'ZenaUser' => 'ZenaUser',
            'ZenaComponent' => 'ZenaComponent',
            'App\\Models\\Zena' => 'App\\Models\\Zena',
            '$zena' => '$zena',
        ];
        
        foreach ($patterns as $name => $pattern) {
            echo "Searching for: $name\n";
            
            $files = [
                'app/Models/*.php',
                'app/Http/Controllers/**/*.php',
                'app/Services/*.php',
                'resources/views/**/*.blade.php',
                'tests/**/*.php',
                'config/*.php',
                'routes/*.php',
                'database/migrations/*.php',
            ];
            
            foreach ($files as $glob) {
                $searchFiles = glob(__DIR__ . '/../' . $glob);
                
                foreach ($searchFiles as $file) {
                    $content = file_get_contents($file);
                    
                    if (strpos($content, $pattern) !== false) {
                        $lines = explode("\n", $content);
                        $lineNumbers = [];
                        
                        foreach ($lines as $lineNum => $line) {
                            if (strpos($line, $pattern) !== false) {
                                $lineNumbers[] = $lineNum + 1;
                            }
                        }
                        
                        echo "   Found in: " . basename($file) . " (lines: " . implode(', ', $lineNumbers) . ")\n";
                    }
                }
            }
            
            echo "\n";
        }
    }
}

// Usage
if (php_sapi_name() === 'cli') {
    $validator = new RefactoringValidator();
    
    $action = $argv[1] ?? 'validate';
    
    switch ($action) {
        case 'validate':
            $validator->validateAll();
            break;
        case 'search':
            $validator->searchPatterns();
            break;
        default:
            echo "Usage: php validate_refactoring.php [validate|search]\n";
            break;
    }
}
