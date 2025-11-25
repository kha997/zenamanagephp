<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * PR #1: Composite unique theo tenant
     * 
     * This migration adds missing composite unique constraints and indexes for tenant isolation:
     * - Documents: unique (tenant_id, name) per tenant (if name should be unique)
     * - Additional composite indexes for performance
     * - Review and ensure FK on-delete rules are correct
     * 
     * Note: This complements existing migrations:
     * - 2025_11_17_143955_add_composite_unique_indexes_with_soft_delete.php (projects, users, template_sets)
     * - 2025_11_18_034512_enforce_tenant_constraints_and_indexes.php (cursor pagination indexes)
     */
    public function up(): void
    {
        // Documents: Add composite unique (tenant_id, name) if name should be unique per tenant
        // Note: Documents typically don't need unique names, but we'll add it as an option
        // Uncomment if documents should have unique names per tenant:
        /*
        if (Schema::hasTable('documents') && Schema::hasColumn('documents', 'name')) {
            $indexName = 'documents_tenant_name_unique';
            if (!$this->hasIndex('documents', $indexName)) {
                Schema::table('documents', function (Blueprint $table) use ($indexName) {
                    $table->unique(['tenant_id', 'name'], $indexName);
                });
            }
        }
        */

        // Quotes: Add composite unique (tenant_id, quote_number) if quote_number exists
        if (Schema::hasTable('quotes')) {
            // Check if quotes table has quote_number or code field
            if (Schema::hasColumn('quotes', 'quote_number')) {
                $indexName = 'quotes_tenant_quote_number_unique';
                if (!$this->hasIndex('quotes', $indexName)) {
                    Schema::table('quotes', function (Blueprint $table) use ($indexName) {
                        $table->unique(['tenant_id', 'quote_number'], $indexName);
                    });
                }
            } elseif (Schema::hasColumn('quotes', 'code')) {
                $indexName = 'quotes_tenant_code_unique';
                if (!$this->hasIndex('quotes', $indexName)) {
                    Schema::table('quotes', function (Blueprint $table) use ($indexName) {
                        $table->unique(['tenant_id', 'code'], $indexName);
                    });
                }
            }
        }

        // Change Requests: Add composite unique (tenant_id, request_number) if request_number exists
        if (Schema::hasTable('change_requests')) {
            if (Schema::hasColumn('change_requests', 'request_number')) {
                $indexName = 'change_requests_tenant_request_number_unique';
                if (!$this->hasIndex('change_requests', $indexName)) {
                    Schema::table('change_requests', function (Blueprint $table) use ($indexName) {
                        $table->unique(['tenant_id', 'request_number'], $indexName);
                    });
                }
            } elseif (Schema::hasColumn('change_requests', 'code')) {
                $indexName = 'change_requests_tenant_code_unique';
                if (!$this->hasIndex('change_requests', $indexName)) {
                    Schema::table('change_requests', function (Blueprint $table) use ($indexName) {
                        $table->unique(['tenant_id', 'code'], $indexName);
                    });
                }
            }
        }

        // Additional composite indexes for performance
        $this->addPerformanceIndexes();
    }

    /**
     * Add composite indexes for common query patterns
     */
    private function addPerformanceIndexes(): void
    {
        // Documents: (tenant_id, project_id, status) for filtering documents by project and status
        if (Schema::hasTable('documents') && Schema::hasColumn('documents', 'status')) {
            $indexName = 'documents_tenant_project_status_index';
            if (!$this->hasIndex('documents', $indexName)) {
                Schema::table('documents', function (Blueprint $table) use ($indexName) {
                    $table->index(['tenant_id', 'project_id', 'status'], $indexName);
                });
            }
        }

        // Documents: (tenant_id, category, status) for filtering by category
        if (Schema::hasTable('documents') && Schema::hasColumn('documents', 'category')) {
            $indexName = 'documents_tenant_category_status_index';
            if (!$this->hasIndex('documents', $indexName)) {
                Schema::table('documents', function (Blueprint $table) use ($indexName) {
                    $table->index(['tenant_id', 'category', 'status'], $indexName);
                });
            }
        }

        // Quotes: (tenant_id, status) for filtering quotes by status
        if (Schema::hasTable('quotes') && Schema::hasColumn('quotes', 'status')) {
            $indexName = 'quotes_tenant_status_index';
            if (!$this->hasIndex('quotes', $indexName)) {
                Schema::table('quotes', function (Blueprint $table) use ($indexName) {
                    $table->index(['tenant_id', 'status'], $indexName);
                });
            }
        }

        // Change Requests: (tenant_id, status) for filtering change requests by status
        if (Schema::hasTable('change_requests') && Schema::hasColumn('change_requests', 'status')) {
            $indexName = 'change_requests_tenant_status_index';
            if (!$this->hasIndex('change_requests', $indexName)) {
                Schema::table('change_requests', function (Blueprint $table) use ($indexName) {
                    $table->index(['tenant_id', 'status'], $indexName);
                });
            }
        }

        // Change Requests: (tenant_id, project_id, status) for project-specific change requests
        if (Schema::hasTable('change_requests') && Schema::hasColumn('change_requests', 'project_id')) {
            $indexName = 'change_requests_tenant_project_status_index';
            if (!$this->hasIndex('change_requests', $indexName)) {
                Schema::table('change_requests', function (Blueprint $table) use ($indexName) {
                    $table->index(['tenant_id', 'project_id', 'status'], $indexName);
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop unique constraints
        if (Schema::hasTable('quotes')) {
            $indexName = 'quotes_tenant_quote_number_unique';
            if ($this->hasIndex('quotes', $indexName)) {
                Schema::table('quotes', function (Blueprint $table) use ($indexName) {
                    $table->dropUnique($indexName);
                });
            }
            $indexName = 'quotes_tenant_code_unique';
            if ($this->hasIndex('quotes', $indexName)) {
                Schema::table('quotes', function (Blueprint $table) use ($indexName) {
                    $table->dropUnique($indexName);
                });
            }
        }

        if (Schema::hasTable('change_requests')) {
            $indexName = 'change_requests_tenant_request_number_unique';
            if ($this->hasIndex('change_requests', $indexName)) {
                Schema::table('change_requests', function (Blueprint $table) use ($indexName) {
                    $table->dropUnique($indexName);
                });
            }
            $indexName = 'change_requests_tenant_code_unique';
            if ($this->hasIndex('change_requests', $indexName)) {
                Schema::table('change_requests', function (Blueprint $table) use ($indexName) {
                    $table->dropUnique($indexName);
                });
            }
        }

        // Drop performance indexes
        $indexes = [
            'documents' => [
                'documents_tenant_project_status_index',
                'documents_tenant_category_status_index',
            ],
            'quotes' => [
                'quotes_tenant_status_index',
            ],
            'change_requests' => [
                'change_requests_tenant_status_index',
                'change_requests_tenant_project_status_index',
            ],
        ];

        foreach ($indexes as $table => $tableIndexes) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            foreach ($tableIndexes as $indexName) {
                if ($this->hasIndex($table, $indexName)) {
                    Schema::table($table, function (Blueprint $table) use ($indexName) {
                        $table->dropIndex($indexName);
                    });
                }
            }
        }
    }

    /**
     * Check if table has index
     */
    private function hasIndex(string $table, string $indexName): bool
    {
        if (!Schema::hasTable($table)) {
            return false;
        }

        $connection = Schema::getConnection();
        $databaseName = $connection->getDatabaseName();
        
        try {
            $result = DB::select(
                "SELECT COUNT(*) as count 
                 FROM information_schema.statistics 
                 WHERE table_schema = ? 
                 AND table_name = ? 
                 AND index_name = ?",
                [$databaseName, $table, $indexName]
            );
            
            return $result[0]->count > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
};
