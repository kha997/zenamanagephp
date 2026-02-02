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
        Schema::create('project_user_roles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('project_id');
            $table->string('user_id');
            $table->string('role_id');
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();

            $table->index(['project_id', 'user_id']);
            $table->index(['role_id']);
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_user_roles');
    }
};
