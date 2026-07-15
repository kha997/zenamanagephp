<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->foreignUlid('opportunity_id')->constrained();
            $table->string('quote_number', 50);
            $table->unsignedInteger('revision_no');
            $table->string('status', 30)->default('draft');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->date('valid_until')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['tenant_id', 'quote_number'], 'quotes_tenant_number_unique');
            $table->unique(['opportunity_id', 'revision_no'], 'quotes_opp_revision_unique');
        });

        Schema::create('quote_line_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->foreignUlid('quote_id')->constrained();
            $table->unsignedInteger('sort_order');
            $table->string('code', 50)->nullable();
            $table->string('name', 255);
            $table->string('unit', 30);
            $table->decimal('quantity', 14, 3);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('amount', 15, 2);
            $table->string('price_note', 500)->nullable();
            $table->timestamps();

            $table->index(['quote_id', 'sort_order'], 'qli_quote_sort_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_line_items');
        Schema::dropIfExists('quotes');
    }
};
