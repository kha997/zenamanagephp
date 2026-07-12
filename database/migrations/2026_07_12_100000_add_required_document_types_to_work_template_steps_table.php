<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_template_steps', function (Blueprint $table) {
            $table->json('required_document_types')->nullable()->after('config_json');
        });
    }

    public function down(): void
    {
        Schema::table('work_template_steps', function (Blueprint $table) {
            $table->dropColumn('required_document_types');
        });
    }
};
