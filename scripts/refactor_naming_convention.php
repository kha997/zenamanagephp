<?php

/**
 * Naming Convention Refactor Script
 * 
 * This script automatically refactors all zena_ prefixed tables and classes
 * to follow Laravel naming conventions.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NamingConventionRefactor
{
    private $mappings = [
        // Table mappings
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
        // Class mappings
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

    private $logFile;
    private $backupDir;

    public function __construct()
    {
        $this->logFile = __DIR__ . '/../storage/logs/refactor_naming_convention.log';
        $this->backupDir = __DIR__ . '/../backups/refactor_' . date('Y-m-d_H-i-s');
        
        // Create backup directory
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
        
        $this->log("Starting naming convention refactor...");
    }

    /**
     * Main refactoring method
     */
    public function refactorAll()
    {
        try {
            $this->log("=== PHASE 1: BACKUP ===");
            $this->createBackup();
            
            $this->log("=== PHASE 2: DATABASE ===");
            $this->refactorDatabase();
            
            $this->log("=== PHASE 3: MODELS ===");
            $this->refactorModels();
            
            $this->log("=== PHASE 4: CONTROLLERS ===");
            $this->refactorControllers();
            
            $this->log("=== PHASE 5: SERVICES ===");
            $this->refactorServices();
            
            $this->log("=== PHASE 6: VIEWS ===");
            $this->refactorViews();
            
            $this->log("=== PHASE 7: TESTS ===");
            $this->refactorTests();
            
            $this->log("=== PHASE 8: CONFIG ===");
            $this->refactorConfigs();
            
            $this->log("=== PHASE 9: VALIDATION ===");
            $this->validateRefactoring();
            
            $this->log("✅ Refactoring completed successfully!");
            
        } catch (Exception $e) {
            $this->log("❌ Error: " . $e->getMessage());
            $this->log("Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Create backup of current state
     */
    private function createBackup()
    {
        $this->log("Creating backup...");
        
        // Backup database
        $backupFile = $this->backupDir . '/database_backup.sql';
        $command = "mysqldump -u root -p zenamanage > " . $backupFile;
        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new Exception("Failed to create database backup");
        }
        
        // Backup critical files
        $filesToBackup = [
            'app/Models',
            'app/Http/Controllers',
            'app/Services',
            'resources/views',
            'tests',
            'config'
        ];
        
        foreach ($filesToBackup as $path) {
            $sourcePath = __DIR__ . '/../' . $path;
            $destPath = $this->backupDir . '/' . basename($path);
            
            if (is_dir($sourcePath)) {
                $this->copyDirectory($sourcePath, $destPath);
            }
        }
        
        $this->log("Backup created at: " . $this->backupDir);
    }

    /**
     * Refactor database tables
     */
    private function refactorDatabase()
    {
        $this->log("Refactoring database tables...");
        
        foreach ($this->mappings as $oldTable => $newTable) {
            if (Schema::hasTable($oldTable)) {
                $this->log("Renaming table: $oldTable → $newTable");
                Schema::rename($oldTable, $newTable);
            }
        }
        
        $this->log("Database refactoring completed");
    }

    /**
     * Refactor Model classes
     */
    private function refactorModels()
    {
        $this->log("Refactoring Model classes...");
        
        $modelFiles = glob(__DIR__ . '/../app/Models/*.php');
        
        foreach ($modelFiles as $file) {
            $this->refactorFile($file, 'Model');
        }
        
        $this->log("Model refactoring completed");
    }

    /**
     * Refactor Controller classes
     */
    private function refactorControllers()
    {
        $this->log("Refactoring Controller classes...");
        
        $controllerFiles = glob(__DIR__ . '/../app/Http/Controllers/**/*.php');
        
        foreach ($controllerFiles as $file) {
            $this->refactorFile($file, 'Controller');
        }
        
        $this->log("Controller refactoring completed");
    }

    /**
     * Refactor Service classes
     */
    private function refactorServices()
    {
        $this->log("Refactoring Service classes...");
        
        $serviceFiles = glob(__DIR__ . '/../app/Services/*.php');
        
        foreach ($serviceFiles as $file) {
            $this->refactorFile($file, 'Service');
        }
        
        $this->log("Service refactoring completed");
    }

    /**
     * Refactor View files
     */
    private function refactorViews()
    {
        $this->log("Refactoring View files...");
        
        $viewFiles = glob(__DIR__ . '/../resources/views/**/*.blade.php');
        
        foreach ($viewFiles as $file) {
            $this->refactorFile($file, 'View');
        }
        
        $this->log("View refactoring completed");
    }

    /**
     * Refactor Test classes
     */
    private function refactorTests()
    {
        $this->log("Refactoring Test classes...");
        
        $testFiles = glob(__DIR__ . '/../tests/**/*.php');
        
        foreach ($testFiles as $file) {
            $this->refactorFile($file, 'Test');
        }
        
        $this->log("Test refactoring completed");
    }

    /**
     * Refactor Config files
     */
    private function refactorConfigs()
    {
        $this->log("Refactoring Config files...");
        
        $configFiles = glob(__DIR__ . '/../config/*.php');
        
        foreach ($configFiles as $file) {
            $this->refactorFile($file, 'Config');
        }
        
        $this->log("Config refactoring completed");
    }

    /**
     * Refactor individual file
     */
    private function refactorFile($filePath, $type)
    {
        if (!file_exists($filePath)) {
            return;
        }
        
        $content = file_get_contents($filePath);
        $originalContent = $content;
        
        // Replace table names
        foreach ($this->mappings as $old => $new) {
            $content = str_replace("'$old'", "'$new'", $content);
            $content = str_replace('"$old"', '"$new"', $content);
            $content = str_replace("`$old`", "`$new`", $content);
        }
        
        // Replace class names
        foreach ($this->classMappings as $old => $new) {
            $content = str_replace($old, $new, $content);
        }
        
        // Replace namespace references
        $content = str_replace('App\\Models\\Zena', 'App\\Models', $content);
        $content = str_replace('use App\\Models\\Zena', 'use App\\Models', $content);
        
        // Replace variable names
        $content = str_replace('$zenaUser', '$user', $content);
        $content = str_replace('$zenaComponent', '$component', $content);
        $content = str_replace('$zenaTaskAssignment', '$taskAssignment', $content);
        
        // Only write if content changed
        if ($content !== $originalContent) {
            file_put_contents($filePath, $content);
            $this->log("Refactored: " . basename($filePath) . " ($type)");
        }
    }

    /**
     * Validate refactoring
     */
    private function validateRefactoring()
    {
        $this->log("Validating refactoring...");
        
        // Check if all tables exist with new names
        $tables = DB::select('SHOW TABLES');
        $tableNames = array_map('current', $tables);
        
        foreach ($this->mappings as $old => $new) {
            if (in_array($old, $tableNames)) {
                throw new Exception("Table $old still exists!");
            }
            if (!in_array($new, $tableNames)) {
                throw new Exception("Table $new does not exist!");
            }
        }
        
        // Check if all models can be instantiated
        $models = ['User', 'Component', 'TaskAssignment', 'Document'];
        
        foreach ($models as $model) {
            try {
                $instance = new $model();
                if (!$instance instanceof \Illuminate\Database\Eloquent\Model) {
                    throw new Exception("Model $model is not valid!");
                }
            } catch (Exception $e) {
                $this->log("Warning: Model $model validation failed: " . $e->getMessage());
            }
        }
        
        $this->log("Validation completed");
    }

    /**
     * Copy directory recursively
     */
    private function copyDirectory($src, $dst)
    {
        if (!is_dir($dst)) {
            mkdir($dst, 0755, true);
        }
        
        $dir = opendir($src);
        while (($file = readdir($dir)) !== false) {
            if ($file != '.' && $file != '..') {
                $srcFile = $src . '/' . $file;
                $dstFile = $dst . '/' . $file;
                
                if (is_dir($srcFile)) {
                    $this->copyDirectory($srcFile, $dstFile);
                } else {
                    copy($srcFile, $dstFile);
                }
            }
        }
        closedir($dir);
    }

    /**
     * Log message
     */
    private function log($message)
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message" . PHP_EOL;
        
        echo $logMessage;
        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
    }

    /**
     * Rollback changes
     */
    public function rollback()
    {
        $this->log("Rolling back changes...");
        
        // Restore database
        $backupFile = $this->backupDir . '/database_backup.sql';
        if (file_exists($backupFile)) {
            $command = "mysql -u root -p zenamanage < " . $backupFile;
            exec($command, $output, $returnCode);
            
            if ($returnCode !== 0) {
                throw new Exception("Failed to restore database backup");
            }
        }
        
        // Restore files
        $filesToRestore = [
            'app/Models',
            'app/Http/Controllers',
            'app/Services',
            'resources/views',
            'tests',
            'config'
        ];
        
        foreach ($filesToRestore as $path) {
            $sourcePath = $this->backupDir . '/' . basename($path);
            $destPath = __DIR__ . '/../' . $path;
            
            if (is_dir($sourcePath)) {
                $this->copyDirectory($sourcePath, $destPath);
            }
        }
        
        $this->log("Rollback completed");
    }
}

// Usage
if (php_sapi_name() === 'cli') {
    $refactor = new NamingConventionRefactor();
    
    $action = $argv[1] ?? 'refactor';
    
    switch ($action) {
        case 'refactor':
            $refactor->refactorAll();
            break;
        case 'rollback':
            $refactor->rollback();
            break;
        default:
            echo "Usage: php refactor_naming_convention.php [refactor|rollback]\n";
            break;
    }
}
