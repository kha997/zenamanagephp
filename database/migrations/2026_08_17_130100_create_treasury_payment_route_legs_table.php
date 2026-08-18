<?php declare(strict_types=1);

use App\Support\Treasury\TreasuryCheckConstraint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        TreasuryCheckConstraint::createTableWithChecks('treasury_payment_route_legs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('payment_route_id');
            $table->unsignedInteger('sequence_no');
            $table->ulid('from_wallet_id')->nullable();
            $table->ulid('to_wallet_id');
            $table->decimal('amount', 15, 2);
            $table->string('status', 16);
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id', 'tprl_tenant_id_fk')
                ->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign(
                ['tenant_id', 'payment_route_id'],
                'tprl_route_fk'
            )->references(['tenant_id', 'id'])->on('treasury_payment_routes');
            $table->foreign(
                ['tenant_id', 'from_wallet_id'],
                'tprl_from_wallet_fk'
            )->references(['tenant_id', 'id'])->on('treasury_wallets');
            $table->foreign(
                ['tenant_id', 'to_wallet_id'],
                'tprl_to_wallet_fk'
            )->references(['tenant_id', 'id'])->on('treasury_wallets');

            $table->unique(['tenant_id', 'id'], 'tprl_tenant_id_id_unique');
            $table->index(['payment_route_id'], 'tprl_route_idx');
        }, [
            'tprl_amount_positive_chk' => 'amount > 0',
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_payment_route_legs');
    }
};
