<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rfis', function (Blueprint $table) {
            $table->ulid('current_escalation_id')->nullable()->after('escalated_at');
            $table->foreign('current_escalation_id')->references('id')->on('rfi_escalations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rfis', function (Blueprint $table) {
            $table->dropForeign(['current_escalation_id']);
            $table->dropColumn('current_escalation_id');
        });
    }
};
