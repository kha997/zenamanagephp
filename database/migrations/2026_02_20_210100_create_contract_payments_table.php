<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contract_payments')) {
            return;
        }

        Schema::create('contract_payments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('tenant_id');
            $table->string('contract_id');
            $table->string('name');
            $table->decimal('amount', 15, 2);
            $table->date('due_date')->nullable();
            $table->string('status')->default('planned');
            $table->date('paid_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('contract_id');
            $table->index(['tenant_id', 'contract_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_payments');
    }
};

