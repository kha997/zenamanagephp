<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('billing_payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('invoice_id')->nullable();
            $table->ulid('subscription_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded', 'cancelled'])->default('pending');
            $table->enum('payment_method', ['stripe', 'paypal', 'bank_transfer', 'manual'])->default('stripe');
            $table->string('payment_reference')->nullable(); // External payment ID
            $table->string('stripe_payment_intent_id')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('invoice_id')->references('id')->on('billing_invoices')->onDelete('set null');
            $table->foreign('subscription_id')->references('id')->on('tenant_subscriptions')->onDelete('set null');
            
            // Indexes
            $table->index(['tenant_id', 'status']);
            $table->index(['status', 'processed_at']);
            $table->index('stripe_payment_intent_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_payments');
    }
};