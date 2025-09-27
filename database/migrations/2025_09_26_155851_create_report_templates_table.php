<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('report_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('tenant_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type'); // dashboard, projects, tasks, team, financial, custom
            $table->string('format'); // pdf, excel, csv
            $table->json('layout'); // template layout configuration
            $table->json('sections'); // report sections configuration
            $table->json('filters')->nullable(); // default filters
            $table->json('styling')->nullable(); // styling options
            $table->boolean('is_public')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('usage_count')->default(0);
            $table->timestamps();
            
            $table->index(['user_id', 'is_active']);
            $table->index(['tenant_id', 'type']);
            $table->index(['is_public', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('report_templates');
    }
};
