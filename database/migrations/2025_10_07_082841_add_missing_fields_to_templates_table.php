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
        Schema::table('templates', function (Blueprint $table) {
            $table->integer('version')->default(1)->after('status');
            $table->boolean('is_public')->default(false)->after('version');
            $table->integer('usage_count')->default(0)->after('is_public');
            $table->json('tags')->nullable()->after('usage_count');
            $table->json('metadata')->nullable()->after('tags');
            $table->json('template_data')->nullable()->after('metadata');
            $table->json('settings')->nullable()->after('template_data');
        });
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