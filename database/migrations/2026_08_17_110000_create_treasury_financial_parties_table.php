<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treasury_financial_parties', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->string('party_type', 32);
            $table->string('name');
            $table->ulid('linked_account_id')->nullable();
            $table->ulid('linked_user_id')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id', 'tfp_tenant_id_fk')
                ->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('linked_account_id', 'tfp_linked_account_fk')
                ->references('id')->on('accounts');
            $table->foreign('linked_user_id', 'tfp_linked_user_fk')
                ->references('id')->on('users');

            $table->unique(['tenant_id', 'id'], 'tfp_tenant_id_id_unique');
            $table->index(['tenant_id'], 'tfp_tenant_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_financial_parties');
    }
};
