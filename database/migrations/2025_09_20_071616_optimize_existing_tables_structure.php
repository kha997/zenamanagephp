<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Optimize users table
        $this->optimizeUsersTable();
        
        // Optimize projects table
        $this->optimizeProjectsTable();
        
        // Optimize tasks table
        $this->optimizeTasksTable();
        
        // Add table optimizations
        $this->addTableOptimizations();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert optimizations if needed
        $this->revertTableOptimizations();
    }

    /**
     * Optimize users table structure
     */
    private function optimizeUsersTable(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Add missing columns if they don't exist
            if (!Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('email_verified_at');
            }
            
            if (!Schema::hasColumn('users', 'login_count')) {
                $table->integer('login_count')->default(0)->after('last_login_at');
            }
            
            if (!Schema::hasColumn('users', 'failed_login_attempts')) {
                $table->integer('failed_login_attempts')->default(0)->after('login_count');
            }
            
            if (!Schema::hasColumn('users', 'locked_until')) {
                $table->timestamp('locked_until')->nullable()->after('failed_login_attempts');
            }
            
            if (!Schema::hasColumn('users', 'password_changed_at')) {
                $table->timestamp('password_changed_at')->nullable()->after('locked_until');
            }
            
            if (!Schema::hasColumn('users', 'password_expires_at')) {
                $table->timestamp('password_expires_at')->nullable()->after('password_changed_at');
            }
            
            // Add performance-related columns
            if (!Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('password_expires_at');
            }
            
            if (!Schema::hasColumn('users', 'timezone')) {
                $table->string('timezone', 50)->default('UTC')->after('is_active');
            }
            
            if (!Schema::hasColumn('users', 'locale')) {
                $table->string('locale', 10)->default('en')->after('timezone');
            }
        });
    }

    /**
     * Optimize projects table structure
     */
    private function optimizeProjectsTable(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // Add missing performance columns
            if (!Schema::hasColumn('projects', 'estimated_hours')) {
                $table->decimal('estimated_hours', 10, 2)->default(0)->after('budget_total');
            }
            
            if (!Schema::hasColumn('projects', 'actual_hours')) {
                $table->decimal('actual_hours', 10, 2)->default(0)->after('estimated_hours');
            }
            
            if (!Schema::hasColumn('projects', 'risk_level')) {
                $table->enum('risk_level', ['low', 'medium', 'high', 'critical'])->default('low')->after('actual_hours');
            }
            
            if (!Schema::hasColumn('projects', 'priority')) {
                $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium')->after('risk_level');
            }
            
            if (!Schema::hasColumn('projects', 'is_template')) {
                $table->boolean('is_template')->default(false)->after('priority');
            }
            
            if (!Schema::hasColumn('projects', 'template_id')) {
                $table->string('template_id')->nullable()->after('is_template');
            }
            
            // Add performance tracking
            if (!Schema::hasColumn('projects', 'last_activity_at')) {
                $table->timestamp('last_activity_at')->nullable()->after('template_id');
            }
            
            if (!Schema::hasColumn('projects', 'completion_percentage')) {
                $table->decimal('completion_percentage', 5, 2)->default(0)->after('last_activity_at');
            }
        });
    }

    /**
     * Optimize tasks table structure
     */
    private function optimizeTasksTable(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Add missing performance columns
            if (!Schema::hasColumn('tasks', 'estimated_cost')) {
                $table->decimal('estimated_cost', 10, 2)->default(0)->after('actual_hours');
            }
            
            if (!Schema::hasColumn('tasks', 'actual_cost')) {
                $table->decimal('actual_cost', 10, 2)->default(0)->after('estimated_cost');
            }
            
            if (!Schema::hasColumn('tasks', 'risk_level')) {
                $table->enum('risk_level', ['low', 'medium', 'high', 'critical'])->default('low')->after('actual_cost');
            }
            
            if (!Schema::hasColumn('tasks', 'complexity')) {
                $table->enum('complexity', ['simple', 'moderate', 'complex', 'very_complex'])->default('moderate')->after('risk_level');
            }
            
            if (!Schema::hasColumn('tasks', 'effort_points')) {
                $table->integer('effort_points')->default(1)->after('complexity');
            }
            
            // Add performance tracking
            if (!Schema::hasColumn('tasks', 'last_activity_at')) {
                $table->timestamp('last_activity_at')->nullable()->after('effort_points');
            }
            
            if (!Schema::hasColumn('tasks', 'time_spent')) {
                $table->decimal('time_spent', 8, 2)->default(0)->after('last_activity_at');
            }
            
            if (!Schema::hasColumn('tasks', 'is_billable')) {
                $table->boolean('is_billable')->default(true)->after('time_spent');
            }
        });
    }

    /**
     * Add table optimizations
     */
    private function addTableOptimizations(): void
    {
        // Skip MySQL-specific statements on other drivers.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $tables = ['users', 'projects', 'tasks'];

        foreach ($tables as $table) {
            try {
                // Set InnoDB settings for better performance
                DB::statement("ALTER TABLE {$table} ENGINE=InnoDB");
                DB::statement("ALTER TABLE {$table} ROW_FORMAT=DYNAMIC");

                // Optimize table
                DB::statement("OPTIMIZE TABLE {$table}");

            } catch (\Exception $e) {
                // Log error but continue with other tables
                \Log::warning("Failed to optimize table {$table}: " . $e->getMessage());
            }
        }
    }

    /**
     * Revert table optimizations
     */
    private function revertTableOptimizations(): void
    {
        // This method can be used to revert specific optimizations if needed
        // For now, we'll leave it empty as most optimizations are additive
    }
};
