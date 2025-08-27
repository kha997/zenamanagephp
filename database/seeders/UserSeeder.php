<?php declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * User Seeder
 * 
 * Tạo dữ liệu mẫu cho users
 * Sử dụng ULID và liên kết với tenant qua ULID
 */
class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaultTenant = Tenant::where('name', 'ZENA Company')->first();
        
        if ($defaultTenant) {
            // Tạo admin user cho tenant mặc định - sử dụng firstOrCreate để tránh trùng lặp
            User::firstOrCreate(
                ['email' => 'admin@zena.local'],
                [
                    'name' => 'Admin User',
                    'password' => Hash::make('password'),
                    'tenant_id' => $defaultTenant->id, // ULID
                    'is_active' => true
                ]
            );

            // Kiểm tra số lượng user hiện có cho tenant này (trừ admin)
            $existingUsersCount = User::where('tenant_id', $defaultTenant->id)
                ->where('email', '!=', 'admin@zena.local')
                ->count();
            
            // Chỉ tạo thêm user nếu chưa đủ 5 user
            if ($existingUsersCount < 5) {
                $usersToCreate = 5 - $existingUsersCount;
                User::factory($usersToCreate)->forTenant($defaultTenant->id)->create();
            }
        }

        // Tạo users cho các tenant khác
        $otherTenants = Tenant::where('name', '!=', 'ZENA Company')->get();
        foreach ($otherTenants as $tenant) {
            // Kiểm tra số lượng user hiện có cho tenant này
            $existingUsersCount = User::where('tenant_id', $tenant->id)->count();
            
            // Chỉ tạo thêm user nếu chưa đủ 3 user
            if ($existingUsersCount < 3) {
                $usersToCreate = 3 - $existingUsersCount;
                User::factory($usersToCreate)->forTenant($tenant->id)->create();
            }
        }
    }
}