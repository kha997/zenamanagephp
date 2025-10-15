<?php

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
        // Only add indexes if they don't already exist
        $this->addIndexIfNotExists('projects', 'tenant_id');
        $this->addIndexIfNotExists('projects', ['tenant_id', 'status']);
        
        $this->addIndexIfNotExists('tasks', 'tenant_id');
        $this->addIndexIfNotExists('tasks', ['tenant_id', 'project_id']);
        
        $this->addIndexIfNotExists('clients', 'tenant_id');
        
        $this->addIndexIfNotExists('quotes', 'tenant_id');
        $this->addIndexIfNotExists('quotes', ['tenant_id', 'client_id']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['tenant_id']);
            $table->dropIndex(['tenant_id', 'status']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['tenant_id']);
            $table->dropIndex(['tenant_id', 'project_id']);
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex(['tenant_id']);
        });

        Schema::table('quotes', function (Blueprint $table) {
            $table->dropIndex(['tenant_id']);
            $table->dropIndex(['tenant_id', 'client_id']);
        });
    }

    private function addIndexIfNotExists(string $table, $columns): void
    {
        $indexName = is_array($columns) 
            ? $table . '_' . implode('_', $columns) . '_index'
            : $table . '_' . $columns . '_index';

        $indexes = DB::select("SHOW INDEX FROM {$table}");
        $indexExists = collect($indexes)->contains('Key_name', $indexName);

        if (!$indexExists) {
            Schema::table($table, function (Blueprint $table) use ($columns) {
                $table->index($columns);
            });
        }
    }
};