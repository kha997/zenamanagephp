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
        if (Schema::hasTable('templates')) {
            Schema::table('templates', function (Blueprint $table) {
                if (!Schema::hasColumn('templates', 'version')) {
                    $table->integer('version')->default(1)->after('status');
                }
                if (!Schema::hasColumn('templates', 'is_public')) {
                    $table->boolean('is_public')->default(false)->after('version');
                }
                if (!Schema::hasColumn('templates', 'usage_count')) {
                    $table->integer('usage_count')->default(0)->after('is_public');
                }
                if (!Schema::hasColumn('templates', 'tags')) {
                    $table->json('tags')->nullable()->after('usage_count');
                }
                if (!Schema::hasColumn('templates', 'metadata')) {
                    $table->json('metadata')->nullable()->after('tags');
                }
                if (!Schema::hasColumn('templates', 'template_data')) {
                    $table->json('template_data')->nullable()->after('metadata');
                }
                if (!Schema::hasColumn('templates', 'settings')) {
                    $table->json('settings')->nullable()->after('template_data');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropColumn([
                'version',
                'is_public',
                'usage_count',
                'tags',
                'metadata',
                'template_data',
                'settings'
            ]);
        });
    }
};