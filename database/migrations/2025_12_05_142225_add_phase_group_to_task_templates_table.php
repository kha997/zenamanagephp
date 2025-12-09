<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Round 209: Add phase/group structure to task templates
     */
    public function up(): void
    {
        Schema::table('task_templates', function (Blueprint $table) {
            $table->string('phase_code', 50)->nullable()->after('order_index');
            $table->string('phase_label', 100)->nullable()->after('phase_code');
            $table->string('group_label', 100)->nullable()->after('phase_label');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_templates', function (Blueprint $table) {
            $table->dropColumn(['phase_code', 'phase_label', 'group_label']);
        });
    }
};
