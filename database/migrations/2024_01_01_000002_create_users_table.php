<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration for users table - basic user structure
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->ulid('id')->primary(); // Sử dụng ULID làm primary key
            $table->foreignUlid('tenant_id')->constrained('tenants')->onDelete('cascade'); // Khóa ngoại đến tenants
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->boolean('is_active')->default(true); // Thêm cột is_active từ model
            $table->json('profile_data')->nullable(); // Thêm cột profile_data từ model
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes(); // Thêm cột deleted_at cho SoftDeletes
            
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
        Schema::dropIfExists('users');
    }
};