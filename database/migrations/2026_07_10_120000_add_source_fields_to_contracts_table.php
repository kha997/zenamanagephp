<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->string('source_opportunity_id')->nullable()->after('project_id');
            $table->string('source_quote_id')->nullable()->after('source_opportunity_id');
            $table->unsignedInteger('source_quote_revision')->nullable()->after('source_quote_id');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->dropColumn(['source_opportunity_id', 'source_quote_id', 'source_quote_revision']);
        });
    }
};
