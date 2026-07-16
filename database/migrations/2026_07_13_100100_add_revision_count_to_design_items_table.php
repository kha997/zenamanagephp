<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('design_items', function (Blueprint $table) {
            $table->unsignedInteger('revision_count')->default(0)->after('approval_evidence');
        });
    }

    public function down(): void
    {
        Schema::table('design_items', function (Blueprint $table) {
            $table->dropColumn('revision_count');
        });
    }
};
