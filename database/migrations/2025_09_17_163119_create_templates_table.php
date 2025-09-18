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
        Schema::create('templates', function (Blueprint $table) {
            $table->string('id')->primary(); // ULID
            $table->string('template_name');
            $table->string('category'); // Design, Construction, QC, Inspection, etc.
            $table->json('json_body'); // Template structure
            $table->integer('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->string('created_by')->nullable(); // ULID
            $table->string('updated_by')->nullable(); // ULID
            $table->timestamps();

            // Foreign keys
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');

            // Indexes
            $table->index(['category', 'is_active']);
            $table->index(['created_by', 'created_at']);
            $table->index('template_name');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('templates');
    }
};
