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
        Schema::table('task_assignments', function (Blueprint $table) {
            // Add team support columns
            $table->ulid('team_id')->nullable()->after('user_id');
            $table->string('assignment_type')->default('user')->after('team_id'); // user, team
            
            // Add foreign key for team
            $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
            
            // Add indexes
            $table->index(['team_id', 'assignment_type']);
            $table->index(['assignment_type']);
            
            // Add constraint to ensure either user_id or team_id is set, but not both
            // Note: MySQL doesn't support CHECK constraints, so we'll handle this in application logic
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_assignments', function (Blueprint $table) {
            // Drop foreign key and indexes
            if (DBDriver::isMysql()) {
                $table->dropForeign(['team_id']);
            }
            $table->dropIndex(['team_id', 'assignment_type']);
            $table->dropIndex(['assignment_type']);
            
            // Drop columns
            $table->dropColumn(['team_id', 'assignment_type']);
        });
    }
};