<?php declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Client Seeder
 * 
 * Tạo dữ liệu mẫu cho clients với tenant isolation
 */
class ClientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('👥 Seeding clients...');

        // Lấy tất cả tenants để tạo clients cho mỗi tenant
        $tenants = Tenant::all();
        
        if ($tenants->isEmpty()) {
            $this->command->warn('No tenants found. Creating default tenant first.');
            $tenants = collect([Tenant::factory()->create()]);
        }

        foreach ($tenants as $tenant) {
            $this->createClientsForTenant($tenant);
        }

        $this->command->info('✅ Clients seeded successfully!');
    }

    /**
     * Tạo clients cho một tenant
     */
    private function createClientsForTenant(Tenant $tenant): void
    {
        $clients = [
            [
                'name' => 'ABC Construction Ltd.',
                'email' => 'contact@abcconstruction.com',
                'phone' => '+84 28 1234 5678',
                'company' => 'ABC Construction Ltd.',
                'lifecycle_stage' => 'customer',
                'address' => '123 Nguyễn Huệ, Quận 1, TP.HCM',
                'notes' => 'Khách hàng VIP, có nhiều dự án lớn',
            ],
            [
                'name' => 'XYZ Development Corp.',
                'email' => 'info@xyzdev.com',
                'phone' => '+84 24 9876 5432',
                'company' => 'XYZ Development Corp.',
                'lifecycle_stage' => 'prospect',
                'address' => '456 Lê Lợi, Quận 3, TP.HCM',
                'notes' => 'Khách hàng tiềm năng, quan tâm đến dự án nhà ở',
            ],
            [
                'name' => 'DEF Architecture Studio',
                'email' => 'hello@defarch.com',
                'phone' => '+84 28 5555 1234',
                'company' => 'DEF Architecture Studio',
                'lifecycle_stage' => 'customer',
                'address' => '789 Đồng Khởi, Quận 1, TP.HCM',
                'notes' => 'Studio thiết kế kiến trúc, hợp tác lâu dài',
            ],
            [
                'name' => 'GHI Real Estate Group',
                'email' => 'sales@ghirealestate.com',
                'phone' => '+84 28 7777 8888',
                'company' => 'GHI Real Estate Group',
                'lifecycle_stage' => 'prospect',
                'address' => '321 Pasteur, Quận 3, TP.HCM',
                'notes' => 'Tập đoàn bất động sản lớn, có nhu cầu thiết kế nhiều dự án',
            ],
            [
                'name' => 'JKL Interior Design',
                'email' => 'contact@jklinterior.com',
                'phone' => '+84 28 9999 0000',
                'company' => 'JKL Interior Design',
                'lifecycle_stage' => 'customer',
                'address' => '654 Nguyễn Thị Minh Khai, Quận 3, TP.HCM',
                'notes' => 'Công ty thiết kế nội thất, chuyên về nhà ở cao cấp',
            ],
            [
                'name' => 'MNO Building Materials',
                'email' => 'info@mnobuilding.com',
                'phone' => '+84 28 1111 2222',
                'company' => 'MNO Building Materials',
                'lifecycle_stage' => 'prospect',
                'address' => '987 Cách Mạng Tháng 8, Quận 10, TP.HCM',
                'notes' => 'Nhà cung cấp vật liệu xây dựng, muốn hợp tác về dự án',
            ],
            [
                'name' => 'PQR Engineering Co.',
                'email' => 'admin@pqrengineering.com',
                'phone' => '+84 28 3333 4444',
                'company' => 'PQR Engineering Co.',
                'lifecycle_stage' => 'customer',
                'address' => '147 Điện Biên Phủ, Quận Bình Thạnh, TP.HCM',
                'notes' => 'Công ty kỹ thuật, chuyên về thiết kế kết cấu',
            ],
            [
                'name' => 'STU Property Management',
                'email' => 'contact@stuproperty.com',
                'phone' => '+84 28 5555 6666',
                'company' => 'STU Property Management',
                'lifecycle_stage' => 'prospect',
                'address' => '258 Võ Văn Tần, Quận 3, TP.HCM',
                'notes' => 'Công ty quản lý bất động sản, có nhu cầu thiết kế',
            ],
            [
                'name' => 'VWX Construction Group',
                'email' => 'info@vwxconstruction.com',
                'phone' => '+84 28 7777 9999',
                'company' => 'VWX Construction Group',
                'lifecycle_stage' => 'customer',
                'address' => '369 Nguyễn Văn Cừ, Quận 5, TP.HCM',
                'notes' => 'Tập đoàn xây dựng lớn, có nhiều dự án trong và ngoài nước',
            ],
            [
                'name' => 'YZA Design Agency',
                'email' => 'hello@yzadesign.com',
                'phone' => '+84 28 8888 1111',
                'company' => 'YZA Design Agency',
                'lifecycle_stage' => 'prospect',
                'address' => '741 Lý Tự Trọng, Quận 1, TP.HCM',
                'notes' => 'Agency thiết kế, chuyên về branding và không gian thương mại',
            ],
        ];

        foreach ($clients as $clientData) {
            Client::create(array_merge($clientData, [
                'tenant_id' => $tenant->id,
            ]));
        }

        $this->command->info("Created " . count($clients) . " clients for tenant: {$tenant->name}");
    }
}
