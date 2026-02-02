<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('teams', 'status')) {
            Schema::table('teams', function (Blueprint $table) {
                $table->string('status')->default('active')->after('department');
                $table->index('status');
            });

            \App\Models\Team::query()->whereNull('status')->update(['status' => 'active']);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('teams', 'status')) {
            Schema::table('teams', function (Blueprint $table) {
                $table->dropIndex(['status']);
                $table->dropColumn('status');
            });
        }
    }
};
