<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_receipts', function (Blueprint $table): void {
            $table->ulid('contract_id')->nullable()->after('vendor_id');
            $table->foreign('contract_id')->references('id')->on('contracts')->nullOnDelete();
            $table->index(['tenant_id', 'project_id', 'contract_id'], 'material_receipts_tenant_project_contract_index');
        });
    }

    public function down(): void
    {
        Schema::table('material_receipts', function (Blueprint $table): void {
            $table->dropIndex('material_receipts_tenant_project_contract_index');
            $table->dropForeign(['contract_id']);
            $table->dropColumn('contract_id');
        });
    }
};
