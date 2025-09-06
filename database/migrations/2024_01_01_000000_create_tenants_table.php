<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateTenantsTable extends Migration
{
    public function up(): void
    {
        // Lưu SQL mode hiện tại
        $currentSqlMode = DB::selectOne('SELECT @@sql_mode as mode')->mode;
        
        // Tắt strict mode tạm thời
        DB::statement("SET sql_mode = ''");
        
        // Tạo bảng bằng Laravel Schema Builder với ULID
        Schema::create('tenants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('domain')->nullable();
            $table->string('database_name', 100)->nullable();
            $table->json('settings')->nullable()->comment('Cấu hình tenant-specific');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->unique('domain');
            $table->index('name');
            $table->index('is_active');
            $table->index(['is_active', 'created_at']);
        });
        
        // Khôi phục SQL mode ban đầu
        DB::statement("SET sql_mode = '{$currentSqlMode}'");
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        Schema::dropIfExists('tenants');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
}