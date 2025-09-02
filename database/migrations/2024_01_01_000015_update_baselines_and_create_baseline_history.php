<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration to update baselines table and create baseline_history table
 * Adds missing linked_contract_id field and creates baseline history tracking
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add missing linked_contract_id to baselines table
        Schema::table('baselines', function (Blueprint $table) {
            $table->foreignUlid('linked_contract_id')->nullable()->after('cost')
                  ->constrained('change_requests')->onDelete('set null')
                  ->comment('Reference to contract change request if applicable');
            
            $table->index(['linked_contract_id']);
        });

        // Create baseline_history table for tracking baseline changes
        Schema::create('baseline_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('baseline_id')->constrained('baselines')->onDelete('cascade');
            $table->integer('from_version')->comment('Previous version number');
            $table->integer('to_version')->comment('New version number');
            $table->text('note')->nullable()->comment('Reason for baseline change');
            $table->foreignUlid('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            
            $table->index(['baseline_id', 'from_version']);
            $table->index(['baseline_id', 'to_version']);
            $table->index(['created_by']);
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('baseline_history');
        
        Schema::table('baselines', function (Blueprint $table) {
            $table->dropForeign(['linked_contract_id']);
            $table->dropIndex(['linked_contract_id']);
            $table->dropColumn('linked_contract_id');
        });
    }
};