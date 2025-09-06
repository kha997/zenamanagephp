<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration for users table - basic user structure
 * Depends on: tenants
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 255)->index();
            $table->string('email', 255)->index();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->boolean('is_active')->default(true)->index();
            $table->json('profile_data')->nullable()->comment('Dữ liệu profile bổ sung');
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
            
            // Database engine và charset
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            
            // Unique constraint cho email trong cùng tenant
            $table->unique(['tenant_id', 'email'], 'unique_tenant_email');
            
            // Indexes for performance
            $table->index(['tenant_id', 'is_active']);
            $table->index(['email', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Tắt foreign key checks để tránh lỗi constraint
        Schema::disableForeignKeyConstraints();
        
        Schema::dropIfExists('users');
        
        // Bật lại foreign key checks
        Schema::enableForeignKeyConstraints();
    }
};