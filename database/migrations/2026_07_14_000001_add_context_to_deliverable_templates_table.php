<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliverable_templates', function (Blueprint $table): void {
            $table->string('context', 50)->default('work_instance')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('deliverable_templates', function (Blueprint $table): void {
            $table->dropColumn('context');
        });
    }
};
