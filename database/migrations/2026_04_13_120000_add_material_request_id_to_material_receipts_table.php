<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_receipts', function (Blueprint $table): void {
            $table->ulid('material_request_id')->nullable()->after('contract_id');
            $table->foreign('material_request_id')->references('id')->on('zena_material_requests')->nullOnDelete();
            $table->index(
                ['tenant_id', 'project_id', 'material_request_id'],
                'material_receipts_tenant_project_material_request_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('material_receipts', function (Blueprint $table): void {
            $table->dropIndex('material_receipts_tenant_project_material_request_index');
            $table->dropForeign(['material_request_id']);
            $table->dropColumn('material_request_id');
        });
    }
};
