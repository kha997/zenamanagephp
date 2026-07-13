<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boqs', function (Blueprint $table): void {
            $table->string('contract_id', 26)->nullable()->index()->after('project_id');
        });
    }

    public function down(): void
    {
        Schema::table('boqs', function (Blueprint $table): void {
            $table->dropIndex(['contract_id']);
            $table->dropColumn('contract_id');
        });
    }
};
