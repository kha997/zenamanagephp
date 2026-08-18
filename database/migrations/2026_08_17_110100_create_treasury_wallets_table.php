<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treasury_wallets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('project_id')->nullable();
            $table->string('wallet_type', 32);
            $table->string('name');
            $table->ulid('custodian_party_id')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id', 'tw_tenant_id_fk')
                ->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('project_id', 'tw_project_id_fk')
                ->references('id')->on('projects');
            $table->foreign(
                ['tenant_id', 'custodian_party_id'],
                'tw_custodian_party_fk'
            )->references(['tenant_id', 'id'])->on('treasury_financial_parties');

            $table->unique(['tenant_id', 'id'], 'tw_tenant_id_id_unique');
            $table->index(['tenant_id'], 'tw_tenant_id_idx');
            $table->index(['project_id'], 'tw_project_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_wallets');
    }
};
