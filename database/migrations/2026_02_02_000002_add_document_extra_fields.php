<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            if (!Schema::hasColumn('documents', 'linked_entity_type')) {
                $table->string('linked_entity_type')->nullable();
            }

            if (!Schema::hasColumn('documents', 'linked_entity_id')) {
                $table->string('linked_entity_id')->nullable();
            }

            if (!Schema::hasColumn('documents', 'tags')) {
                $table->json('tags')->nullable();
            }

            if (!Schema::hasColumn('documents', 'visibility')) {
                $table->string('visibility')->default('internal');
            }

            if (!Schema::hasColumn('documents', 'client_approved')) {
                $table->boolean('client_approved')->default(false);
            }

            if (!Schema::hasColumn('documents', 'current_version_id')) {
                $table->string('current_version_id')->nullable();
            }

            if (!Schema::hasColumn('documents', 'updated_by')) {
                $table->string('updated_by')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            if (Schema::hasColumn('documents', 'linked_entity_type')) {
                $table->dropColumn('linked_entity_type');
            }

            if (Schema::hasColumn('documents', 'linked_entity_id')) {
                $table->dropColumn('linked_entity_id');
            }

            if (Schema::hasColumn('documents', 'tags')) {
                $table->dropColumn('tags');
            }

            if (Schema::hasColumn('documents', 'visibility')) {
                $table->dropColumn('visibility');
            }

            if (Schema::hasColumn('documents', 'client_approved')) {
                $table->dropColumn('client_approved');
            }

            if (Schema::hasColumn('documents', 'current_version_id')) {
                $table->dropColumn('current_version_id');
            }

            if (Schema::hasColumn('documents', 'updated_by')) {
                $table->dropColumn('updated_by');
            }
        });
    }
};
