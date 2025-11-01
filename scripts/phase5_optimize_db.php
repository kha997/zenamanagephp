<?php

/**
 * PHASE 5: Script tối ưu hóa database và performance
 */

$basePath = '/Applications/XAMPP/xamppfiles/htdocs/zenamanage';

echo "⚡ PHASE 5: TỐI ƯU HÓA DATABASE & PERFORMANCE\n";
echo "============================================\n\n";

$optimizedFiles = 0;
$errors = 0;

// 1. Tạo migration để thêm missing indexes
echo "1️⃣ Tạo migration để thêm missing indexes...\n";

$indexMigration = "<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Add missing indexes for better performance
        Schema::table('tenants', function (Blueprint \$table) {
            \$table->index('status');
        });
        
        Schema::table('zena_components', function (Blueprint \$table) {
            \$table->index('status');
            \$table->index('project_id');
        });
        
        Schema::table('zena_task_assignments', function (Blueprint \$table) {
            \$table->index('status');
            \$table->index('user_id');
            \$table->index('task_id');
        });
        
        Schema::table('zena_documents', function (Blueprint \$table) {
            \$table->index('status');
            \$table->index('project_id');
        });
        
        Schema::table('tasks', function (Blueprint \$table) {
            \$table->index('tenant_id');
            \$table->index('project_id');
            \$table->index('status');
            \$table->index('assignee_id');
            \$table->index('created_at');
        });
        
        Schema::table('projects', function (Blueprint \$table) {
            \$table->index('tenant_id');
            \$table->index('status');
            \$table->index('created_at');
        });
        
        Schema::table('users', function (Blueprint \$table) {
            \$table->index('tenant_id');
            \$table->index('email');
            \$table->index('is_active');
        });
        
        Schema::table('notifications', function (Blueprint \$table) {
            \$table->index('user_id');
            \$table->index('read_at');
            \$table->index('created_at');
        });
        
        Schema::table('audit_logs', function (Blueprint \$table) {
            \$table->index('user_id');
            \$table->index('auditable_type');
            \$table->index('created_at');
        });
    }

    public function down()
    {
        // Remove indexes
        Schema::table('tenants', function (Blueprint \$table) {
            \$table->dropIndex(['status']);
        });
        
        Schema::table('zena_components', function (Blueprint \$table) {
            \$table->dropIndex(['status']);
            \$table->dropIndex(['project_id']);
        });
        
        Schema::table('zena_task_assignments', function (Blueprint \$table) {
            \$table->dropIndex(['status']);
            \$table->dropIndex(['user_id']);
            \$table->dropIndex(['task_id']);
        });
        
        Schema::table('zena_documents', function (Blueprint \$table) {
            \$table->dropIndex(['status']);
            \$table->dropIndex(['project_id']);
        });
        
        Schema::table('tasks', function (Blueprint \$table) {
            \$table->dropIndex(['tenant_id']);
            \$table->dropIndex(['project_id']);
            \$table->dropIndex(['status']);
            \$table->dropIndex(['assignee_id']);
            \$table->dropIndex(['created_at']);
        });
        
        Schema::table('projects', function (Blueprint \$table) {
            \$table->dropIndex(['tenant_id']);
            \$table->dropIndex(['status']);
            \$table->dropIndex(['created_at']);
        });
        
        Schema::table('users', function (Blueprint \$table) {
            \$table->dropIndex(['tenant_id']);
            \$table->dropIndex(['email']);
            \$table->dropIndex(['is_active']);
        });
        
        Schema::table('notifications', function (Blueprint \$table) {
            \$table->dropIndex(['user_id']);
            \$table->dropIndex(['read_at']);
            \$table->dropIndex(['created_at']);
        });
        
        Schema::table('audit_logs', function (Blueprint \$table) {
            \$table->dropIndex(['user_id']);
            \$table->dropIndex(['auditable_type']);
            \$table->dropIndex(['created_at']);
        });
    }
};";

$migrationPath = $basePath . '/database/migrations/' . date('Y_m_d_His') . '_add_performance_indexes.php';
if (file_put_contents($migrationPath, $indexMigration)) {
    echo "  ✅ Created migration: " . basename($migrationPath) . "\n";
    $optimizedFiles++;
} else {
    echo "  ❌ Failed to create migration\n";
    $errors++;
}

// 2. Tối ưu hóa Controllers
echo "\n2️⃣ Tối ưu hóa Controllers...\n";

$controllersToOptimize = [
    'app/Http/Controllers/Web/AnalyticsController.php',
    'app/Http/Controllers/Api/AuthController.php',
    'app/Http/Controllers/ComponentController.php',
    'app/Http/Controllers/DocumentController.php',
];

foreach ($controllersToOptimize as $controllerPath) {
    $fullPath = $basePath . '/' . $controllerPath;
    
    if (!file_exists($fullPath)) {
        echo "  ⚠️ Not found: {$controllerPath}\n";
        continue;
    }
    
    $content = file_get_contents($fullPath);
    $originalContent = $content;
    
    // Tối ưu hóa queries
    $content = preg_replace('/::all\(\)/', '::paginate(15)', $content);
    $content = preg_replace('/->get\(\)/', '->with([\'user\', \'project\'])->get()', $content);
    $content = preg_replace('/->where\([\'"]([^\'"]+)[\'"]/', '->select([\'id\', \'name\', \'status\'])->where(\'$1\'', $content);
    
    if ($content !== $originalContent) {
        if (file_put_contents($fullPath, $content)) {
            echo "  ✅ Optimized: {$controllerPath}\n";
            $optimizedFiles++;
        } else {
            echo "  ❌ Failed: {$controllerPath}\n";
            $errors++;
        }
    } else {
        echo "  ⚠️ No changes needed: {$controllerPath}\n";
    }
}

// 3. Tối ưu hóa Services
echo "\n3️⃣ Tối ưu hóa Services...\n";

$servicesToOptimize = [
    'app/Services/AuditService.php',
    'app/Services/BulkOperationsService.php',
    'app/Services/ComponentService.php',
];

foreach ($servicesToOptimize as $servicePath) {
    $fullPath = $basePath . '/' . $servicePath;
    
    if (!file_exists($fullPath)) {
        echo "  ⚠️ Not found: {$servicePath}\n";
        continue;
    }
    
    $content = file_get_contents($fullPath);
    $originalContent = $content;
    
    // Thêm caching
    $content = preg_replace('/->get\(\)/', '->remember(300)->get()', $content);
    
    // Thêm chunking cho large datasets
    $content = preg_replace('/->get\(\)/', '->chunk(1000, function($items) {', $content);
    
    if ($content !== $originalContent) {
        if (file_put_contents($fullPath, $content)) {
            echo "  ✅ Optimized: {$servicePath}\n";
            $optimizedFiles++;
        } else {
            echo "  ❌ Failed: {$servicePath}\n";
            $errors++;
        }
    } else {
        echo "  ⚠️ No changes needed: {$servicePath}\n";
    }
}

// 4. Tạo cache configuration
echo "\n4️⃣ Tạo cache configuration...\n";

$cacheConfig = "<?php

return [
    'default' => env('CACHE_DRIVER', 'file'),
    
    'stores' => [
        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
        ],
        
        'redis' => [
            'driver' => 'redis',
            'connection' => 'cache',
        ],
        
        'database' => [
            'driver' => 'database',
            'table' => 'cache',
            'connection' => null,
        ],
    ],
    
    'prefix' => env('CACHE_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_cache'),
];";

$configPath = $basePath . '/config/cache.php';
if (file_put_contents($configPath, $cacheConfig)) {
    echo "  ✅ Updated cache config\n";
    $optimizedFiles++;
} else {
    echo "  ❌ Failed to update cache config\n";
    $errors++;
}

// 5. Tạo database optimization service
echo "\n5️⃣ Tạo database optimization service...\n";

$optimizationService = "<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class DatabaseOptimizationService
{
    /**
     * Optimize database tables
     */
    public function optimizeTables(): array
    {
        \$tables = [
            'users', 'projects', 'tasks', 'notifications', 
            'audit_logs', 'zena_components', 'zena_documents'
        ];
        
        \$results = [];
        
        foreach (\$tables as \$table) {
            try {
                DB::statement(\"OPTIMIZE TABLE {\$table}\");
                \$results[\$table] = 'optimized';
            } catch (\\Exception \$e) {
                \$results[\$table] = 'error: ' . \$e->getMessage();
            }
        }
        
        return \$results;
    }
    
    /**
     * Analyze table performance
     */
    public function analyzeTables(): array
    {
        \$tables = [
            'users', 'projects', 'tasks', 'notifications', 
            'audit_logs', 'zena_components', 'zena_documents'
        ];
        
        \$results = [];
        
        foreach (\$tables as \$table) {
            try {
                \$result = DB::select(\"ANALYZE TABLE {\$table}\");
                \$results[\$table] = \$result[0] ?? 'analyzed';
            } catch (\\Exception \$e) {
                \$results[\$table] = 'error: ' . \$e->getMessage();
            }
        }
        
        return \$results;
    }
    
    /**
     * Clear query cache
     */
    public function clearQueryCache(): bool
    {
        try {
            DB::statement('FLUSH QUERY CACHE');
            Cache::flush();
            return true;
        } catch (\\Exception \$e) {
            return false;
        }
    }
    
    /**
     * Get slow queries
     */
    public function getSlowQueries(): array
    {
        try {
            return DB::select('SHOW FULL PROCESSLIST');
        } catch (\\Exception \$e) {
            return [];
        }
    }
}";

$servicePath = $basePath . '/app/Services/DatabaseOptimizationService.php';
if (file_put_contents($servicePath, $optimizationService)) {
    echo "  ✅ Created DatabaseOptimizationService\n";
    $optimizedFiles++;
} else {
    echo "  ❌ Failed to create DatabaseOptimizationService\n";
    $errors++;
}

echo "\n📊 KẾT QUẢ TỐI ƯU HÓA:\n";
echo "======================\n";
echo "  ✅ Files optimized: {$optimizedFiles}\n";
echo "  ❌ Errors: {$errors}\n\n";

echo "🎯 Hoàn thành tối ưu hóa PHASE 5!\n";
