<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('change_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('change_requests', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->after('requested_at');
            }
            if (!Schema::hasColumn('change_requests', 'approval_comments')) {
                $table->text('approval_comments')->nullable()->after('rejection_reason');
            }
            if (!Schema::hasColumn('change_requests', 'rejection_comments')) {
                $table->text('rejection_comments')->nullable()->after('approval_comments');
            }
            if (!Schema::hasColumn('change_requests', 'approved_cost')) {
                $table->decimal('approved_cost', 15, 2)->nullable()->after('rejection_comments');
            }
            if (!Schema::hasColumn('change_requests', 'approved_schedule_days')) {
                $table->integer('approved_schedule_days')->nullable()->after('approved_cost');
            }
        });
    }

    public function down(): void
    {
        Schema::table('change_requests', function (Blueprint $table) {
            $table->dropColumn([
                'submitted_at',
                'approval_comments',
                'rejection_comments',
                'approved_cost',
                'approved_schedule_days',
            ]);
        });
    }
};
