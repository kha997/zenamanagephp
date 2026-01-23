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
        Schema::table('qc_inspections', function (Blueprint $table) {
            $table->timestamp('scheduled_at')->nullable()->after('inspection_date');
            $table->timestamp('conducted_at')->nullable()->after('scheduled_at');
            $table->timestamp('completed_at')->nullable()->after('conducted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('qc_inspections', function (Blueprint $table) {
            $table->dropColumn(['scheduled_at', 'conducted_at', 'completed_at']);
        });
    }
};
