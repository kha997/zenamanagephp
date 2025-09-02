<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Thêm các trường bổ sung cho bảng users (tenant_id đã tồn tại)
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // Thêm các trường bổ sung theo yêu cầu project
            $table->string('phone', 20)->nullable()->after('email');
            $table->string('avatar_url')->nullable()->after('phone');
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active')->after('avatar_url');
            $table->timestamp('last_login_at')->nullable()->after('status');
            
            // Tạo unique constraint cho email trong cùng tenant (nếu chưa có)
            $table->unique(['tenant_id', 'email'], 'users_tenant_email_unique');
            
            // Tạo index để tối ưu hóa truy vấn
            $table->index(['tenant_id', 'status'], 'users_tenant_status_index');
            $table->index('last_login_at', 'users_last_login_index');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            // Xóa các index và constraint trước
            $table->dropIndex('users_last_login_index');
            $table->dropIndex('users_tenant_status_index');
            $table->dropUnique('users_tenant_email_unique');
            
            // Xóa các cột đã thêm (không xóa tenant_id vì nó đã tồn tại từ trước)
            $table->dropColumn([
                'phone',
                'avatar_url', 
                'status',
                'last_login_at'
            ]);
        });
    }
};
