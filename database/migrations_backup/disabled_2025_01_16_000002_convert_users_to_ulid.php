<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Chuyển đổi bảng users sang sử dụng ULID thay vì auto-increment ID
 * Phụ thuộc vào migration convert_tenants_to_ulid đã chạy trước
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Tạo bảng users mới với ULID
        Schema::create('users_new', function (Blueprint $table) {
            // Primary key sử dụng ULID
            $table->ulid('id')->primary();
            
            // Các cột cơ bản
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            
            // Các cột từ update migration
            $table->ulid('tenant_id'); // Sử dụng ULID cho foreign key
            $table->boolean('is_active')->default(true);
            $table->text('profile_data')->nullable();
            
            $table->timestamps();
            
            // Foreign key constraint
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            
            // Indexes
            $table->index(['tenant_id', 'email']);
            
            // Engine và charset
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
        });
        
        // Copy dữ liệu từ bảng cũ sang bảng mới (nếu có)
        // Lưu ý: Trong môi trường test, bảng có thể trống
        if (Schema::hasTable('users')) {
            // Lấy tenant_id đầu tiên có sẵn để gán cho users cũ
            $firstTenantId = DB::table('tenants')->value('id');
            
            if ($firstTenantId) {
                DB::statement('
                    INSERT INTO users_new (id, name, email, email_verified_at, password, remember_token, tenant_id, is_active, profile_data, created_at, updated_at)
                    SELECT 
                        LOWER(CONCAT(
                            SUBSTR(HEX(RANDOM_BYTES(5)), 1, 10),
                            SUBSTR(HEX(RANDOM_BYTES(5)), 1, 4),
                            SUBSTR(HEX(RANDOM_BYTES(5)), 1, 4),
                            SUBSTR(HEX(RANDOM_BYTES(5)), 1, 4),
                            SUBSTR(HEX(RANDOM_BYTES(5)), 1, 12)
                        )) as id,
                        name, email, email_verified_at, password, remember_token,
                        COALESCE(
                            (SELECT t.id FROM tenants t WHERE t.id = CAST(users.tenant_id AS CHAR) LIMIT 1),
                            ?) as tenant_id,
                        COALESCE(is_active, 1) as is_active,
                        profile_data,
                        created_at, updated_at
                    FROM users
                ', [$firstTenantId]);
            }
        }
        
        // Drop bảng cũ và rename bảng mới
        Schema::dropIfExists('users');
        Schema::rename('users_new', 'users');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Tạo lại bảng với auto-increment ID
        Schema::create('users_old', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->boolean('is_active')->default(true);
            $table->text('profile_data')->nullable();
            $table->timestamps();
            
            $table->index(['tenant_id', 'email']);
            
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
        });
        
        // Copy dữ liệu ngược lại (mất ULID)
        DB::statement('
            INSERT INTO users_old (name, email, email_verified_at, password, remember_token, tenant_id, is_active, profile_data, created_at, updated_at)
            SELECT name, email, email_verified_at, password, remember_token, 
                   (SELECT id FROM tenants WHERE tenants.id = users.tenant_id LIMIT 1) as tenant_id,
                   is_active, profile_data, created_at, updated_at
            FROM users
        ');
        
        Schema::dropIfExists('users');
        Schema::rename('users_old', 'users');
    }
};