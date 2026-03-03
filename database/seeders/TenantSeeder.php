<?php declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

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
        Tenant::firstOrCreate(
            ['domain' => 'zena.local'], // Điều kiện tìm kiếm
            [
                'name' => 'ZENA Company',
                'slug' => 'zena-company',
                'is_active' => true,
                'status' => 'active',
                'settings' => [
                    'timezone' => 'Asia/Ho_Chi_Minh',
                    'currency' => 'VND',
                    'language' => 'vi'
                ]
            ]
        );

        // Kiểm tra và tạo thêm tenant khác nếu chưa đủ
        $existingCount = Tenant::where('domain', '!=', 'zena.local')->count();
        if ($existingCount < 2) {
            foreach (array_slice($this->sampleTenants(), 0, 2 - $existingCount) as $tenant) {
                Tenant::firstOrCreate(
                    ['domain' => $tenant['domain']],
                    $tenant
                );
            }
        }
    }

    /**
     * Seed deterministic non-default tenants so db:seed works without faker/dev dependencies.
     *
     * @return array<int, array<string, mixed>>
     */
    private function sampleTenants(): array
    {
        return [
            [
                'name' => 'ZENA Studio',
                'slug' => 'zena-studio',
                'domain' => 'studio.zena.local',
                'is_active' => true,
                'status' => 'active',
                'settings' => [
                    'timezone' => 'Asia/Ho_Chi_Minh',
                    'currency' => 'VND',
                    'language' => 'vi',
                ],
            ],
            [
                'name' => 'ZENA Build',
                'slug' => 'zena-build',
                'domain' => 'build.zena.local',
                'is_active' => true,
                'status' => 'active',
                'settings' => [
                    'timezone' => 'Asia/Ho_Chi_Minh',
                    'currency' => 'VND',
                    'language' => 'vi',
                ],
            ],
        ];
    }
}
