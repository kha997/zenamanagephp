<?php

use App\Traits\SkipsSchemaIntrospection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    use SkipsSchemaIntrospection;

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (self::shouldSkipSchemaIntrospection()) {
            return;
        }

        Schema::table('components', function (Blueprint $table) {
            // Add missing fields only if they don't exist
            if (!Schema::hasColumn('components', 'tenant_id')) {
                $table->string('tenant_id')->nullable()->after('id');
            }
            if (!Schema::hasColumn('components', 'priority')) {
                $table->string('priority')->default('medium')->after('status');
            }
            if (!Schema::hasColumn('components', 'start_date')) {
                $table->date('start_date')->nullable()->after('priority');
            }
            if (!Schema::hasColumn('components', 'end_date')) {
                $table->date('end_date')->nullable()->after('start_date');
            }
            if (!Schema::hasColumn('components', 'budget')) {
                $table->decimal('budget', 15, 2)->default(0.00)->after('end_date');
            }
            if (!Schema::hasColumn('components', 'dependencies')) {
                $table->json('dependencies')->nullable()->after('budget');
            }
            if (!Schema::hasColumn('components', 'created_by')) {
                $table->string('created_by')->nullable()->after('dependencies');
            }
        });

        // Add indexes only if columns exist
        Schema::table('components', function (Blueprint $table) {
            if (Schema::hasColumn('components', 'tenant_id') && !$this->indexExists('components', 'components_tenant_id_index')) {
                $table->index(['tenant_id']);
            }
            if (Schema::hasColumn('components', 'priority') && !$this->indexExists('components', 'components_priority_index')) {
                $table->index(['priority']);
            }
            if (Schema::hasColumn('components', 'start_date') && !$this->indexExists('components', 'components_start_date_index')) {
                $table->index(['start_date']);
            }
            if (Schema::hasColumn('components', 'end_date') && !$this->indexExists('components', 'components_end_date_index')) {
                $table->index(['end_date']);
            }
        });

        // Add foreign keys only if columns exist
        Schema::table('components', function (Blueprint $table) {
            if (Schema::hasColumn('components', 'tenant_id') && !$this->foreignKeyExists('components', 'components_tenant_id_foreign')) {
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            }
            if (Schema::hasColumn('components', 'created_by') && !$this->foreignKeyExists('components', 'components_created_by_foreign')) {
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            }
        });
    }

    /**
     * Check if index exists
     */
    private function indexExists(string $table, string $index): bool
    {
        try {
            $driver = DB::getDriverName();
            
            if ($driver === 'sqlite') {
                $indexes = DB::select("PRAGMA index_list({$table})");
                foreach ($indexes as $idx) {
                    if ($idx->name === $index) {
                        return true;
                    }
                }
                return false;
            } else {
                $indexes = DB::select("SHOW INDEX FROM {$table}");
                foreach ($indexes as $idx) {
                    if ($idx->Key_name === $index) {
                        return true;
                    }
                }
                return false;
            }
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Check if foreign key exists
     */
    private function foreignKeyExists(string $table, string $foreignKey): bool
    {
        try {
            $driver = DB::getDriverName();
            
            if ($driver === 'sqlite') {
                $foreignKeys = DB::select("PRAGMA foreign_key_list({$table})");
                foreach ($foreignKeys as $fk) {
                    if ($fk->id === $foreignKey) {
                        return true;
                    }
                }
                return false;
            } else {
                $foreignKeys = DB::select("
                    SELECT CONSTRAINT_NAME 
                    FROM information_schema.KEY_COLUMN_USAGE 
                    WHERE TABLE_NAME = '{$table}' 
                    AND CONSTRAINT_NAME = '{$foreignKey}'
                ");
                return !empty($foreignKeys);
            }
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('components', function (Blueprint $table) {
            // Drop foreign keys first
            $table->dropForeign(['tenant_id']);
            $table->dropForeign(['created_by']);
            
            // Drop indexes
            $table->dropIndex(['tenant_id']);
            $table->dropIndex(['priority']);
            $table->dropIndex(['start_date']);
            $table->dropIndex(['end_date']);
            
            // Drop columns
            $table->dropColumn([
                'tenant_id',
                'priority',
                'start_date',
                'end_date',
                'budget',
                'dependencies',
                'created_by'
            ]);
        });
    }
};
