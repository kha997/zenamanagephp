<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (!Schema::hasColumn('projects', 'order')) {
                $table->integer('order')->default(0)->after('status');
            }
        });

        // Set initial order based on updated_at for existing projects
        // Projects are grouped by status and ordered by updated_at desc
        $statuses = ['planning', 'active', 'on_hold', 'completed', 'cancelled'];
        
        foreach ($statuses as $status) {
            $projects = DB::table('projects')
                ->where('status', $status)
                ->orderBy('updated_at', 'desc')
                ->get();
            
            $order = 0;
            foreach ($projects as $project) {
                DB::table('projects')
                    ->where('id', $project->id)
                    ->update(['order' => $order++]);
            }
        }

        // Add index for better performance when sorting by status and order
        Schema::table('projects', function (Blueprint $table) {
            $table->index(['status', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['status', 'order']);
            $table->dropColumn('order');
        });
    }
};
