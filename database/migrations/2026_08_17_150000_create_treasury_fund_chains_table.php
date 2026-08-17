<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treasury_fund_chains', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('project_id');
            $table->string('chain_reference');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id', 'tfc_tenant_id_fk')
                ->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('project_id', 'tfc_project_id_fk')
                ->references('id')->on('projects');

            $table->unique(['tenant_id', 'id'], 'tfc_tenant_id_id_unique');
            $table->index(['tenant_id'], 'tfc_tenant_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_fund_chains');
    }
};
