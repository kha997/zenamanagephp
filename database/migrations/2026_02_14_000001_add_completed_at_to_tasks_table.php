<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('tasks', 'completed_at')) {
            Schema::table('tasks', function (Blueprint $table): void {
                $table->timestamp('completed_at')->nullable()->after('end_date');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tasks', 'completed_at')) {
            Schema::table('tasks', function (Blueprint $table): void {
                $table->dropColumn('completed_at');
            });
        }
    }
};
