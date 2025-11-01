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
        Schema::create('file_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('version_number');
            $table->string('path');
            $table->string('disk')->default('local');
            $table->bigInteger('size');
            $table->string('hash');
            $table->text('change_description')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_current')->default(false);
            $table->timestamps();
            
            $table->index(['file_id', 'is_current']);
            $table->index(['user_id', 'created_at']);
            $table->unique(['file_id', 'version_number']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('file_versions');
    }
};
