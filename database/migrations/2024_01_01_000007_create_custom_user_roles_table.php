<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration for custom user roles
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('custom_user_roles', function (Blueprint $table) {
            $table->foreignUlid('user_id')->constrained()->onDelete('cascade');
            $table->foreignUlid('role_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            $table->primary(['user_id', 'role_id']);
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
        Schema::dropIfExists('custom_user_roles');
    }
};