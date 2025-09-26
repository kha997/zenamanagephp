<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Fix foreign key constraints for documents table
        if (Schema::hasTable('documents')) {
            // Drop existing foreign key constraints if they exist
            try {
                Schema::table('documents', function (Blueprint $table) {
                    $table->dropForeign(['project_id']);
                });
            } catch (\Exception $e) {
                // Foreign key might not exist
            }

            try {
                Schema::table('documents', function (Blueprint $table) {
                    $table->dropForeign(['uploaded_by']);
                });
            } catch (\Exception $e) {
                // Foreign key might not exist
            }

            // Add correct foreign key constraints
            Schema::table('documents', function (Blueprint $table) {
                $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
                $table->foreign('uploaded_by')->references('id')->on('users')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('documents')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->dropForeign(['project_id']);
                $table->dropForeign(['uploaded_by']);
            });
        }
    }
};