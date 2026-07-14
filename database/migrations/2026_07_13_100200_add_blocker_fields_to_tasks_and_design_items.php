<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['tasks', 'design_items'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->timestamp('blocked_at')->nullable();
                $table->string('blocker_note', 1000)->nullable();
                $table->string('blocked_by')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['tasks', 'design_items'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn(['blocked_at', 'blocker_note', 'blocked_by']);
            });
        }
    }
};
