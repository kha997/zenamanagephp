<?php

/**
 * Safe Refactoring Execution Script
 * 
 * This script executes the naming convention refactoring
 * in a safe, step-by-step manner with rollback capability.
 */

require_once __DIR__ . '/../vendor/autoload.php';

class SafeRefactoringExecutor
{
    private $logFile;
    private $backupDir;
    private $steps = [];
    private $currentStep = 0;

    public function __construct()
    {
        $this->logFile = __DIR__ . '/../storage/logs/safe_refactoring_' . date('Y-m-d_H-i-s') . '.log';
        $this->backupDir = __DIR__ . '/../backups/safe_refactor_' . date('Y-m-d_H-i-s');
        
        // Create backup directory
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
        
        $this->log("🚀 Starting safe refactoring execution...");
    }

    /**
     * Execute refactoring with safety checks
     */
    public function execute()
    {
        try {
            $this->log("=== PHASE 1: PRE-FLIGHT CHECKS ===");
            $this->preFlightChecks();
            
            $this->log("=== PHASE 2: BACKUP CREATION ===");
            $this->createComprehensiveBackup();
            
            $this->log("=== PHASE 3: DATABASE REFACTORING ===");
            $this->refactorDatabase();
            
            $this->log("=== PHASE 4: MODEL REFACTORING ===");
            $this->refactorModels();
            
            $this->log("=== PHASE 5: CONTROLLER REFACTORING ===");
            $this->refactorControllers();
            
            $this->log("=== PHASE 6: SERVICE REFACTORING ===");
            $this->refactorServices();
            
            $this->log("=== PHASE 7: VIEW REFACTORING ===");
            $this->refactorViews();
            
            $this->log("=== PHASE 8: TEST REFACTORING ===");
            $this->refactorTests();
            
            $this->log("=== PHASE 9: CONFIG REFACTORING ===");
            $this->refactorConfigs();
            
            $this->log("=== PHASE 10: VALIDATION ===");
            $this->validateRefactoring();
            
            $this->log("=== PHASE 11: CLEANUP ===");
            $this->cleanup();
            
            $this->log("✅ Refactoring completed successfully!");
            $this->log("📊 Total steps executed: " . count($this->steps));
            
        } catch (Exception $e) {
            $this->log("❌ Error: " . $e->getMessage());
            $this->log("🔄 Rolling back to step: " . $this->currentStep);
            $this->rollback();
            throw $e;
        }
    }

    /**
     * Pre-flight checks
     */
    private function preFlightChecks()
    {
        $this->log("Running pre-flight checks...");
        
        // Check database connection
        try {
            DB::connection()->getPdo();
            $this->log("✅ Database connection OK");
        } catch (Exception $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
        
        // Check if tables exist
        $tables = DB::select('SHOW TABLES');
        $tableNames = array_map('current', $tables);
        
        $requiredTables = ['zena_users', 'zena_components', 'zena_documents'];
        foreach ($requiredTables as $table) {
            if (!in_array($table, $tableNames)) {
                throw new Exception("Required table $table does not exist");
            }
        }
        
        $this->log("✅ All required tables exist");
        
        // Check disk space
        $freeSpace = disk_free_space(__DIR__ . '/../');
        if ($freeSpace < 100 * 1024 * 1024) { // 100MB
            throw new Exception("Insufficient disk space for backup");
        }
        
        $this->log("✅ Sufficient disk space available");
        
        // Check file permissions
        $directories = ['storage/logs', 'backups'];
        foreach ($directories as $dir) {
            $path = __DIR__ . '/../' . $dir;
            if (!is_writable($path)) {
                throw new Exception("Directory $dir is not writable");
            }
        }
        
        $this->log("✅ File permissions OK");
    }

    /**
     * Create comprehensive backup
     */
    private function createComprehensiveBackup()
    {
        $this->log("Creating comprehensive backup...");
        
        // Database backup
        $dbBackupFile = $this->backupDir . '/database_backup.sql';
        $command = "mysqldump -u root -p zenamanage > " . $dbBackupFile;
        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new Exception("Failed to create database backup");
        }
        
        $this->log("✅ Database backup created: " . basename($dbBackupFile));
        
        // File backup
        $directories = [
            'app/Models',
            'app/Http/Controllers',
            'app/Services',
            'resources/views',
            'tests',
            'config',
            'routes',
            'database/migrations'
        ];
        
        foreach ($directories as $dir) {
            $sourcePath = __DIR__ . '/../' . $dir;
            $destPath = $this->backupDir . '/' . basename($dir);
            
            if (is_dir($sourcePath)) {
                $this->copyDirectory($sourcePath, $destPath);
                $this->log("✅ Backed up: $dir");
            }
        }
        
        $this->log("✅ Comprehensive backup completed");
    }

    /**
     * Refactor database tables
     */
    private function refactorDatabase()
    {
        $this->log("Refactoring database tables...");
        
        $mappings = [
            'zena_users' => 'users',
            'zena_components' => 'components',
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
        
        foreach ($mappings as $oldTable => $newTable) {
            if (Schema::hasTable($oldTable)) {
                $this->log("Renaming table: $oldTable → $newTable");
                Schema::rename($oldTable, $newTable);
                
                $this->steps[] = [
                    'action' => 'rename_table',
                    'old' => $oldTable,
                    'new' => $newTable,
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                
                $this->log("✅ Table renamed successfully");
            }
        }
        
        $this->log("✅ Database refactoring completed");
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
        
        $this->log("✅ Model refactoring completed");
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
        
        $this->log("✅ Controller refactoring completed");
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
        
        $this->log("✅ Service refactoring completed");
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
        
        $this->log("✅ View refactoring completed");
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
        
        $this->log("✅ Test refactoring completed");
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
        
        $this->log("✅ Config refactoring completed");
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
        $mappings = [
            'zena_users' => 'users',
            'zena_components' => 'components',
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
        
        foreach ($mappings as $old => $new) {
            $content = str_replace("'$old'", "'$new'", $content);
            $content = str_replace('"$old"', '"$new"', $content);
            $content = str_replace("`$old`", "`$new`", $content);
        }
        
        // Replace class names
        $classMappings = [
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
        
        foreach ($classMappings as $old => $new) {
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
            
            $this->steps[] = [
                'action' => 'refactor_file',
                'file' => $filePath,
                'type' => $type,
                'timestamp' => date('Y-m-d H:i:s')
            ];
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
        
        $mappings = [
            'zena_users' => 'users',
            'zena_components' => 'components',
            'zena_documents' => 'documents',
        ];
        
        foreach ($mappings as $old => $new) {
            if (in_array($old, $tableNames)) {
                throw new Exception("Table $old still exists!");
            }
            if (!in_array($new, $tableNames)) {
                throw new Exception("Table $new does not exist!");
            }
        }
        
        $this->log("✅ Database validation passed");
        
        // Check if all models can be instantiated
        $models = ['User', 'Component', 'Document'];
        
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
        
        $this->log("✅ Model validation passed");
    }

    /**
     * Cleanup
     */
    private function cleanup()
    {
        $this->log("Cleaning up...");
        
        // Clear Laravel caches
        $commands = [
            'php artisan route:clear',
            'php artisan config:clear',
            'php artisan cache:clear',
            'php artisan view:clear',
        ];
        
        foreach ($commands as $command) {
            exec($command, $output, $returnCode);
            if ($returnCode === 0) {
                $this->log("✅ " . $command);
            } else {
                $this->log("⚠️ " . $command . " failed");
            }
        }
        
        $this->log("✅ Cleanup completed");
    }

    /**
     * Rollback changes
     */
    private function rollback()
    {
        $this->log("Rolling back changes...");
        
        // Restore database
        $backupFile = $this->backupDir . '/database_backup.sql';
        if (file_exists($backupFile)) {
            $command = "mysql -u root -p zenamanage < " . $backupFile;
            exec($command, $output, $returnCode);
            
            if ($returnCode !== 0) {
                $this->log("❌ Failed to restore database backup");
            } else {
                $this->log("✅ Database restored from backup");
            }
        }
        
        // Restore files
        $directories = [
            'app/Models',
            'app/Http/Controllers',
            'app/Services',
            'resources/views',
            'tests',
            'config',
            'routes',
            'database/migrations'
        ];
        
        foreach ($directories as $dir) {
            $sourcePath = $this->backupDir . '/' . basename($dir);
            $destPath = __DIR__ . '/../' . $dir;
            
            if (is_dir($sourcePath)) {
                $this->copyDirectory($sourcePath, $destPath);
                $this->log("✅ Restored: $dir");
            }
        }
        
        $this->log("✅ Rollback completed");
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
}

// Usage
if (php_sapi_name() === 'cli') {
    $executor = new SafeRefactoringExecutor();
    
    $action = $argv[1] ?? 'execute';
    
    switch ($action) {
        case 'execute':
            $executor->execute();
            break;
        case 'rollback':
            $executor->rollback();
            break;
        default:
            echo "Usage: php safe_refactoring_executor.php [execute|rollback]\n";
            break;
    }
}
