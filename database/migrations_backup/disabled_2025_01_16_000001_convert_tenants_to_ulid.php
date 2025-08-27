<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Chuyển đổi bảng tenants sang sử dụng ULID thay vì auto-increment ID
 * Đây là bước đầu tiên trong việc chuẩn hóa toàn bộ database sang ULID
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Tạm thời disable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        
        // Drop tất cả foreign key constraints tham chiếu đến tenants
        $this->dropForeignKeyConstraints();
        
        // Tạo bảng tenants mới với ULID
        Schema::create('tenants_new', function (Blueprint $table) {
            // Primary key sử dụng ULID
            $table->ulid('id')->primary();
            
            // Các cột khác giữ nguyên
            $table->string('name');
            $table->string('domain')->nullable()->unique();
            $table->string('database_name')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Engine và charset
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
        });
        
        // Copy dữ liệu từ bảng cũ sang bảng mới (nếu có)
        if (Schema::hasTable('tenants')) {
            $tenants = DB::table('tenants')->get();
            
            foreach ($tenants as $tenant) {
                DB::table('tenants_new')->insert([
                    'id' => Str::ulid()->toBase32(),
                    'name' => $tenant->name,
                    'domain' => $tenant->domain,
                    'database_name' => $tenant->database_name,
                    'settings' => $tenant->settings,
                    'is_active' => $tenant->is_active,
                    'created_at' => $tenant->created_at,
                    'updated_at' => $tenant->updated_at,
                ]);
            }
        }
        
        // Drop bảng cũ và rename bảng mới
        Schema::dropIfExists('tenants');
        Schema::rename('tenants_new', 'tenants');
        
        // Tạo lại foreign key constraints
        $this->recreateForeignKeyConstraints();
        
        // Bật lại foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    /**
     * Drop các foreign key constraints tham chiếu đến bảng tenants
     */
    private function dropForeignKeyConstraints(): void
    {
        // Drop foreign key từ bảng users nếu tồn tại
        if (Schema::hasTable('users')) {
            try {
                Schema::table('users', function (Blueprint $table) {
                    $table->dropForeign(['tenant_id']);
                });
            } catch (\Exception $e) {
                // Ignore nếu foreign key không tồn tại
            }
        }
        
        // Drop foreign key từ bảng projects nếu tồn tại
        if (Schema::hasTable('projects')) {
            try {
                Schema::table('projects', function (Blueprint $table) {
                    $table->dropForeign(['tenant_id']);
                });
            } catch (\Exception $e) {
                // Ignore nếu foreign key không tồn tại
            }
        }
    }
    
    /**
     * Tạo lại foreign key constraints sau khi rename
     */
    private function recreateForeignKeyConstraints(): void
    {
        // Tạo lại foreign key cho bảng users nếu tồn tại
        if (Schema::hasTable('users')) {
            try {
                Schema::table('users', function (Blueprint $table) {
                    $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
                });
            } catch (\Exception $e) {
                // Ignore nếu foreign key đã tồn tại
            }
        }
        
        // Tạo lại foreign key cho bảng projects nếu tồn tại
        if (Schema::hasTable('projects')) {
            try {
                Schema::table('projects', function (Blueprint $table) {
                    $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
                });
            } catch (\Exception $e) {
                // Ignore nếu foreign key đã tồn tại
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Tạm thời disable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        
        // Drop foreign key constraints
        $this->dropForeignKeyConstraints();
        
        // Tạo lại bảng với auto-increment ID
        Schema::create('tenants_old', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('domain')->nullable()->unique();
            $table->string('database_name')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
        });
        
        // Copy dữ liệu ngược lại (mất ULID)
        $tenants = DB::table('tenants')->get();
        
        foreach ($tenants as $tenant) {
            DB::table('tenants_old')->insert([
                'name' => $tenant->name,
                'domain' => $tenant->domain,
                'database_name' => $tenant->database_name,
                'settings' => $tenant->settings,
                'is_active' => $tenant->is_active,
                'created_at' => $tenant->created_at,
                'updated_at' => $tenant->updated_at,
            ]);
        }
        
        Schema::dropIfExists('tenants');
        Schema::rename('tenants_old', 'tenants');
        
        // Tạo lại foreign key constraints với auto-increment ID
        if (Schema::hasTable('users')) {
            try {
                Schema::table('users', function (Blueprint $table) {
                    $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
                });
            } catch (\Exception $e) {
                // Ignore nếu foreign key đã tồn tại
            }
        }
        
        if (Schema::hasTable('projects')) {
            try {
                Schema::table('projects', function (Blueprint $table) {
                    $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
                });
            } catch (\Exception $e) {
                // Ignore nếu foreign key đã tồn tại
            }
        }
        
        // Bật lại foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
};