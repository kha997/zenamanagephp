<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration for baselines table - project baseline management
 * Manages contract and execution baselines for projects
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('baselines', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('project_id')->constrained('projects')->onDelete('cascade');
            $table->enum('type', ['contract', 'execution'])->comment('Baseline type');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('cost', 15, 2)->default(0.00);
            $table->integer('version')->default(1)->comment('Baseline version number');
            $table->text('note')->nullable()->comment('Baseline description/notes');
            $table->foreignUlid('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            
            $table->index(['project_id', 'type']);
            $table->index(['project_id', 'version']);
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
        Schema::dropIfExists('baselines');
    }
};