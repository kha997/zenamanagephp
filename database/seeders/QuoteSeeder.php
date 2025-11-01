<?php declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Quote;
use App\Models\Client;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Quote Seeder
 * 
 * Tạo dữ liệu mẫu cho quotes với tenant isolation
 */
class QuoteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('📋 Seeding quotes...');

        // Lấy tất cả tenants để tạo quotes cho mỗi tenant
        $tenants = Tenant::all();
        
        if ($tenants->isEmpty()) {
            $this->command->warn('No tenants found. Skipping quotes seeding.');
            return;
        }

        foreach ($tenants as $tenant) {
            $this->createQuotesForTenant($tenant);
        }

        $this->command->info('✅ Quotes seeded successfully!');
    }

    /**
     * Tạo quotes cho một tenant
     */
    private function createQuotesForTenant(Tenant $tenant): void
    {
        // Lấy clients của tenant này
        $clients = Client::where('tenant_id', $tenant->id)->withoutGlobalScopes()->get();
        
        if ($clients->isEmpty()) {
            $this->command->warn("No clients found for tenant: {$tenant->name}. Skipping quotes.");
            return;
        }

        // Lấy users của tenant này
        $users = \App\Models\User::where('tenant_id', $tenant->id)->withoutGlobalScopes()->get();
        
        if ($users->isEmpty()) {
            $this->command->warn("No users found for tenant: {$tenant->name}. Skipping quotes.");
            return;
        }

        $quotes = [
            [
                'title' => 'Thiết kế kiến trúc nhà ở 2 tầng',
                'type' => 'design',
                'description' => 'Thiết kế kiến trúc nhà ở 2 tầng với diện tích 150m²',
                'total_amount' => 25000000,
                'final_amount' => 25000000,
                'status' => 'sent',
                'valid_until' => now()->addDays(30),
                'terms_conditions' => 'Bao gồm thiết kế kiến trúc, kết cấu và MEP',
            ],
            [
                'title' => 'Xây dựng nhà ở 3 tầng',
                'type' => 'construction',
                'description' => 'Xây dựng nhà ở 3 tầng với diện tích 200m²',
                'total_amount' => 850000000,
                'final_amount' => 850000000,
                'status' => 'accepted',
                'valid_until' => now()->addDays(45),
                'terms_conditions' => 'Bao gồm vật liệu và nhân công',
            ],
            [
                'title' => 'Cải tạo căn hộ chung cư',
                'type' => 'construction',
                'description' => 'Cải tạo và nâng cấp căn hộ chung cư',
                'total_amount' => 45000000,
                'final_amount' => 45000000,
                'status' => 'draft',
                'valid_until' => now()->addDays(15),
                'terms_conditions' => 'Thiết kế nội thất và thi công',
            ],
            [
                'title' => 'Thiết kế văn phòng làm việc',
                'type' => 'design',
                'description' => 'Thiết kế văn phòng làm việc 500m²',
                'total_amount' => 75000000,
                'final_amount' => 75000000,
                'status' => 'viewed',
                'valid_until' => now()->addDays(20),
                'terms_conditions' => 'Thiết kế không gian làm việc hiện đại',
            ],
            [
                'title' => 'Xây dựng nhà xưởng sản xuất',
                'type' => 'construction',
                'description' => 'Xây dựng nhà xưởng sản xuất 1000m²',
                'total_amount' => 1200000000,
                'final_amount' => 1200000000,
                'status' => 'rejected',
                'valid_until' => now()->subDays(5),
                'terms_conditions' => 'Nhà xưởng công nghiệp với hệ thống MEP',
            ],
            [
                'title' => 'Thiết kế nội thất nhà hàng',
                'type' => 'design',
                'description' => 'Thiết kế nội thất nhà hàng cao cấp',
                'total_amount' => 120000000,
                'final_amount' => 120000000,
                'status' => 'sent',
                'valid_until' => now()->addDays(25),
                'terms_conditions' => 'Thiết kế không gian nhà hàng sang trọng',
            ],
            [
                'title' => 'Thiết kế biệt thự nghỉ dưỡng',
                'type' => 'design',
                'description' => 'Thiết kế biệt thự nghỉ dưỡng',
                'total_amount' => 180000000,
                'final_amount' => 180000000,
                'status' => 'accepted',
                'valid_until' => now()->addDays(60),
                'terms_conditions' => 'Thiết kế biệt thự với hồ bơi và sân vườn',
            ],
            [
                'title' => 'Cải tạo mặt tiền cửa hàng',
                'type' => 'construction',
                'description' => 'Cải tạo mặt tiền cửa hàng',
                'total_amount' => 35000000,
                'final_amount' => 35000000,
                'status' => 'draft',
                'valid_until' => now()->addDays(10),
                'terms_conditions' => 'Thiết kế và thi công mặt tiền thương mại',
            ],
            [
                'title' => 'Xây dựng trường học 2 tầng',
                'type' => 'construction',
                'description' => 'Xây dựng trường học 2 tầng',
                'total_amount' => 950000000,
                'final_amount' => 950000000,
                'status' => 'viewed',
                'valid_until' => now()->addDays(40),
                'terms_conditions' => 'Trường học với 20 phòng học và sân chơi',
            ],
            [
                'title' => 'Thiết kế nội thất penthouse',
                'type' => 'design',
                'description' => 'Thiết kế nội thất căn hộ penthouse',
                'total_amount' => 200000000,
                'final_amount' => 200000000,
                'status' => 'sent',
                'valid_until' => now()->addDays(35),
                'terms_conditions' => 'Thiết kế nội thất cao cấp cho penthouse',
            ],
        ];

        foreach ($quotes as $quoteData) {
            // Chọn client ngẫu nhiên cho quote này
            $client = $clients->random();
            
            Quote::create(array_merge($quoteData, [
                'tenant_id' => $tenant->id,
                'client_id' => $client->id,
                'created_by' => $users->random()->id,
            ]));
        }

        $this->command->info("Created " . count($quotes) . " quotes for tenant: {$tenant->name}");
    }
}
