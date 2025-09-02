<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Cập nhật cấu trúc bảng tenants theo yêu cầu project
     *
     * @return void
     */
    public function up()
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Kiểm tra và thêm các cột nếu chưa tồn tại
            if (!Schema::hasColumn('tenants', 'slug')) {
                $table->string('slug', 100)->unique()->after('name');
            }
            
            if (!Schema::hasColumn('tenants', 'domain')) {
                $table->string('domain')->nullable()->unique()->after('slug');
            }
            
            if (!Schema::hasColumn('tenants', 'settings')) {
                $table->json('settings')->nullable()->after('domain');
            }
            
            if (!Schema::hasColumn('tenants', 'status')) {
                $table->enum('status', ['active', 'inactive', 'trial', 'suspended'])
                      ->default('trial')
                      ->after('settings');
            }
            
            if (!Schema::hasColumn('tenants', 'trial_ends_at')) {
                $table->timestamp('trial_ends_at')->nullable()->after('status');
            }
            
            // Tạo các index để tối ưu hóa truy vấn
            $table->index('status', 'tenants_status_index');
            $table->index('trial_ends_at', 'tenants_trial_ends_index');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Xóa các index trước
            $table->dropIndex('tenants_trial_ends_index');
            $table->dropIndex('tenants_status_index');
            
            // Xóa các cột đã thêm (chỉ xóa nếu tồn tại)
            if (Schema::hasColumn('tenants', 'trial_ends_at')) {
                $table->dropColumn('trial_ends_at');
            }
            
            if (Schema::hasColumn('tenants', 'status')) {
                $table->dropColumn('status');
            }
            
            if (Schema::hasColumn('tenants', 'settings')) {
                $table->dropColumn('settings');
            }
            
            if (Schema::hasColumn('tenants', 'domain')) {
                $table->dropColumn('domain');
            }
            
            if (Schema::hasColumn('tenants', 'slug')) {
                $table->dropColumn('slug');
            }
        });
    }
};
