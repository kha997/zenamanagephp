<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('change_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('change_requests', 'impact_cost')) {
                $table->decimal('impact_cost', 15, 2)->default(0);
            }

            if (!Schema::hasColumn('change_requests', 'impact_days')) {
                $table->integer('impact_days')->default(0);
            }

            if (!Schema::hasColumn('change_requests', 'impact_kpi')) {
                $table->json('impact_kpi')->nullable();
            }
            if (!Schema::hasColumn('change_requests', 'created_by')) {
                $table->string('created_by')->nullable();
            }
            if (!Schema::hasColumn('change_requests', 'decided_by')) {
                $table->string('decided_by')->nullable();
            }
            if (!Schema::hasColumn('change_requests', 'decided_at')) {
                $table->timestamp('decided_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('change_requests', function (Blueprint $table) {
            if (Schema::hasColumn('change_requests', 'impact_kpi')) {
                $table->dropColumn('impact_kpi');
            }

            if (Schema::hasColumn('change_requests', 'impact_days')) {
                $table->dropColumn('impact_days');
            }

            if (Schema::hasColumn('change_requests', 'impact_cost')) {
                $table->dropColumn('impact_cost');
            }
            if (Schema::hasColumn('change_requests', 'created_by')) {
                $table->dropColumn('created_by');
            }
            if (Schema::hasColumn('change_requests', 'decided_by')) {
                $table->dropColumn('decided_by');
            }
            if (Schema::hasColumn('change_requests', 'decided_at')) {
                $table->dropColumn('decided_at');
            }
        });
    }
};
