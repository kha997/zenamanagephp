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
        Schema::create('document_versions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('document_id');
            $table->integer('version_number');
            $table->string('file_path');
            $table->string('storage_driver')->default('local');
            $table->text('comment')->nullable();
            $table->json('metadata')->nullable();
            $table->ulid('created_by');
            $table->integer('reverted_from_version_number')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['document_id', 'version_number']);
            $table->index(['document_id', 'created_at']);
            $table->index(['storage_driver']);
            $table->index(['created_by']);

            // Foreign Keys
            $table->foreign('document_id')->references('id')->on('documents')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');

            // Unique constraint
            $table->unique(['document_id', 'version_number']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('document_versions');
    }
};