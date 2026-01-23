<?php declare(strict_types=1);

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
        Schema::table('change_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('change_requests', 'cost_impact')) {
                $table->decimal('cost_impact', 15, 2)->nullable()->after('impact_analysis');
            }

            if (!Schema::hasColumn('change_requests', 'schedule_impact_days')) {
                $table->integer('schedule_impact_days')->nullable()->after('cost_impact');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('change_requests', function (Blueprint $table) {
            if (Schema::hasColumn('change_requests', 'schedule_impact_days')) {
                $table->dropColumn('schedule_impact_days');
            }

            if (Schema::hasColumn('change_requests', 'cost_impact')) {
                $table->dropColumn('cost_impact');
            }
        });
    }
};
