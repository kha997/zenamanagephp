<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Support\DBDriver;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('project_milestones', function (Blueprint $table) {
            $table->json('metadata')->nullable()->after('order');
            $table->string('created_by')->nullable()->after('metadata');
            
            // Add foreign key for created_by
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_milestones', function (Blueprint $table) {
            if (DBDriver::isMysql()) {
                $table->dropForeign(['created_by']);
            }
            $table->dropColumn(['metadata', 'created_by']);
        });
    }
};