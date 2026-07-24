<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submittals', function (Blueprint $table) {
            $table->unsignedInteger('current_revision_no')->nullable()->after('status');
        });

        // 'revised' was declared on the model but never reachable in production code
        // (submit() only ever accepted 'draft'). This is a safety net, not an expected no-op.
        DB::table('submittals')->where('status', 'revised')->update(['status' => 'rejected']);
    }

    public function down(): void
    {
        Schema::table('submittals', function (Blueprint $table) {
            $table->dropColumn('current_revision_no');
        });
    }
};
