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
        Schema::create('report_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('tenant_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type'); // dashboard, projects, tasks, team, financial
            $table->string('format'); // pdf, excel, csv
            $table->string('frequency'); // daily, weekly, monthly
            $table->json('recipients'); // array of email addresses
            $table->json('filters')->nullable(); // report filters
            $table->json('options')->nullable(); // export options
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamp('next_send_at')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'is_active']);
            $table->index(['tenant_id', 'next_send_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('report_schedules');
    }
};
