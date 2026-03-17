<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('documents')) {
            return;
        }

        if (Schema::hasColumn('documents', 'title')) {
            return;
        }

        Schema::table('documents', function (Blueprint $table) {
            $table->string('title')->nullable()->after('original_name');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('documents') || !Schema::hasColumn('documents', 'title')) {
            return;
        }

        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('title');
        });
    }
};
