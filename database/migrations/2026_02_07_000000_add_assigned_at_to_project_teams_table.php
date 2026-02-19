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
        Schema::table('project_teams', function (Blueprint $table) {
            if (!Schema::hasColumn('project_teams', 'assigned_at')) {
                $table->timestamp('assigned_at')->nullable()->after('role');
                $table->index('assigned_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_teams', function (Blueprint $table) {
            if (Schema::hasColumn('project_teams', 'assigned_at')) {
                $table->dropIndex(['assigned_at']);
                $table->dropColumn('assigned_at');
            }
        });
    }
};
