<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('tasks', 'dependencies') && !Schema::hasColumn('tasks', 'dependencies_json')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->renameColumn('dependencies', 'dependencies_json');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tasks', 'dependencies_json') && !Schema::hasColumn('tasks', 'dependencies')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->renameColumn('dependencies_json', 'dependencies');
            });
        }
    }
};
