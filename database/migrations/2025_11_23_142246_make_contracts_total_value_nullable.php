<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Round 46: Hardening & Polish - Allow contract.total_value to be null
     * This allows contracts to exist without a total value (edge case handling)
     */
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->decimal('total_value', 15, 2)->nullable()->default(null)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Set all null values to 0 before making column not nullable
        \DB::table('contracts')->whereNull('total_value')->update(['total_value' => 0]);
        
        Schema::table('contracts', function (Blueprint $table) {
            $table->decimal('total_value', 15, 2)->default(0)->change();
        });
    }
};
