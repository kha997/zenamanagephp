<?php declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Tenant Seeder
 * 
 * Tạo dữ liệu mẫu cho các tenant (công ty/tổ chức)
 * Sử dụng ULID làm primary key
 */
class TenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Tạo tenant mặc định cho development - sử dụng firstOrCreate để tránh duplicate
        $tenant = Tenant::withoutGlobalScopes()->where('domain', 'zena.local')->first();
        if (!$tenant) {
            Tenant::create([
                'domain' => 'zena.local',
                'name' => 'ZENA Company',
                'slug' => 'zena-company',
                'is_active' => true,
                'status' => 'active',
                'settings' => [
                    'timezone' => 'Asia/Ho_Chi_Minh',
                    'currency' => 'VND',
                    'language' => 'vi'
                ]
            ]);
        }

        // Kiểm tra và tạo thêm tenant khác nếu chưa đủ
        $existingCount = Tenant::withoutGlobalScopes()->where('domain', '!=', 'zena.local')->count();
        if ($existingCount < 2) {
            $needToCreate = 2 - $existingCount;
            Tenant::factory($needToCreate)->create();
        }
    }
}