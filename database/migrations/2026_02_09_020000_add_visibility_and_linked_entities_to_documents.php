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
        if (!Schema::hasTable('documents')) {
            return;
        }

        Schema::table('documents', function (Blueprint $table) {
            if (!Schema::hasColumn('documents', 'visibility')) {
                $table->string('visibility')->default('internal')->after('description');
            }

            if (!Schema::hasColumn('documents', 'client_approved')) {
                $table->boolean('client_approved')->default(false)->after('visibility');
            }

            if (!Schema::hasColumn('documents', 'linked_entity_type')) {
                $table->string('linked_entity_type')->nullable()->after('client_approved');
            }

            if (!Schema::hasColumn('documents', 'linked_entity_id')) {
                $table->string('linked_entity_id')->nullable()->after('linked_entity_type');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('documents')) {
            return;
        }

        Schema::table('documents', function (Blueprint $table) {
            if (Schema::hasColumn('documents', 'linked_entity_id')) {
                $table->dropColumn('linked_entity_id');
            }

            if (Schema::hasColumn('documents', 'linked_entity_type')) {
                $table->dropColumn('linked_entity_type');
            }

            if (Schema::hasColumn('documents', 'client_approved')) {
                $table->dropColumn('client_approved');
            }

            if (Schema::hasColumn('documents', 'visibility')) {
                $table->dropColumn('visibility');
            }
        });
    }
};
