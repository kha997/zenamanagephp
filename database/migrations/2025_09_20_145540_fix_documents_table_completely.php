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
        // Drop all existing foreign key constraints
        $this->dropForeignKeys();
        
        // Recreate correct foreign key constraints
        $this->createForeignKeys();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropForeignKeys();
    }

    /**
     * Drop all foreign key constraints
     */
    private function dropForeignKeys(): void
    {
        $foreignKeys = [
            'documents_project_id_foreign',
            'zena_documents_parent_document_id_foreign',
            'zena_documents_tenant_id_foreign',
            'documents_uploaded_by_foreign',
            'documents_created_by_foreign',
            'zena_documents_project_id_foreign',
            'zena_documents_uploaded_by_foreign',
            'zena_documents_created_by_foreign',
        ];

        foreach ($foreignKeys as $fk) {
            try {
                Schema::table('documents', function (Blueprint $table) use ($fk) {
                    $table->dropForeign($fk);
                });
            } catch (\Exception $e) {
                // Foreign key might not exist, continue
            }
        }
    }

    /**
     * Create correct foreign key constraints
     */
    private function createForeignKeys(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            // Project foreign key
            $table->foreign('project_id')
                  ->references('id')
                  ->on('projects')
                  ->onDelete('cascade')
                  ->onUpdate('cascade');

            // Tenant foreign key
            $table->foreign('tenant_id')
                  ->references('id')
                  ->on('tenants')
                  ->onDelete('cascade')
                  ->onUpdate('cascade');

            // Uploaded by foreign key
            $table->foreign('uploaded_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade')
                  ->onUpdate('cascade');

            // Creator foreign key
            $table->foreign('created_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null')
                  ->onUpdate('cascade');

            // Parent document foreign key
            $table->foreign('parent_document_id')
                  ->references('id')
                  ->on('documents')
                  ->onDelete('set null')
                  ->onUpdate('cascade');
        });
    }
};
