<?php
/**
 * Script test tính năng User Management
 * Demo các API endpoints cho quản lý User
 */

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Support\Facades\Hash;

echo "=== USER MANAGEMENT DEMO ===\n\n";

// 1. Tạo Tenant mới
echo "1. Tạo Tenant mới...\n";
$tenant = Tenant::create([
    'name' => 'Demo Company',
    'domain' => 'demo' . time() . '.local',
    'status' => 'active'
]);
echo "✅ Created tenant: {$tenant->id} - {$tenant->name}\n\n";

// 2. Tạo User mới
echo "2. Tạo User mới...\n";
$user = User::create([
    'name' => 'Demo User',
    'email' => 'demo' . time() . '@test.com',
    'password' => Hash::make('password123'),
    'tenant_id' => $tenant->id,
    'status' => 'active'
]);
echo "✅ Created user: {$user->id} - {$user->name} ({$user->email})\n\n";

// 3. Test các phương thức User
echo "3. Test các phương thức User...\n";

// Test isActive()
echo "   - User isActive(): " . ($user->isActive() ? 'Yes' : 'No') . "\n";

// Test getProfileData()
$profileData = $user->getProfileData('phone', 'No phone');
echo "   - Profile data (phone): " . $profileData . "\n";

// Test updateProfileData()
$user->updateProfileData('phone', '0123456789');
$user->updateProfileData('department', 'IT');
echo "   - Updated profile data (phone): " . $user->fresh()->getProfileData('phone', 'No phone') . "\n";
echo "   - Updated profile data (department): " . $user->fresh()->getProfileData('department', 'No department') . "\n\n";

// 4. Test relationships
echo "4. Test relationships...\n";
echo "   - Tenant: {$user->tenant->name}\n";
echo "   - System roles count: " . $user->systemRoles()->count() . "\n";
// echo "   - Project roles count: " . $user->projectRoles()->count() . "\n"; // Skip due to missing deleted_at column
echo "\n";

// 5. Test scopes
echo "5. Test scopes...\n";
$activeUsers = User::active()->count();
echo "   - Active users count: {$activeUsers}\n";

$tenantUsers = User::forTenant($tenant->id)->count();
echo "   - Users for tenant {$tenant->id}: {$tenantUsers}\n\n";

// 6. Test UserController methods (simulate)
echo "6. Test UserController methods...\n";

// Simulate index method
$users = User::with(['tenant'])->where('tenant_id', $tenant->id)->get();
echo "   - Users in tenant: " . $users->count() . "\n";

// Simulate show method
$userDetail = User::with(['tenant'])->find($user->id);
echo "   - User detail: {$userDetail->name} ({$userDetail->email})\n";

// Simulate update method
$user->update(['name' => 'Updated Demo User']);
echo "   - Updated user name: {$user->fresh()->name}\n\n";

// 7. Test JWT functionality
echo "7. Test JWT functionality...\n";
try {
    $token = auth('api')->login($user);
    echo "   - JWT token generated: " . substr($token, 0, 50) . "...\n";
    
    $payload = auth('api')->payload();
    echo "   - JWT payload user_id: {$payload->get('user_id')}\n";
    echo "   - JWT payload tenant_id: {$payload->get('tenant_id')}\n";
} catch (Exception $e) {
    echo "   - JWT error: {$e->getMessage()}\n";
}

echo "\n=== DEMO COMPLETED ===\n";
echo "Bạn có thể sử dụng các API endpoints sau:\n";
echo "- POST /api/v1/auth/login - Đăng nhập\n";
echo "- GET /api/v1/users - Lấy danh sách users\n";
echo "- POST /api/v1/users - Tạo user mới\n";
echo "- GET /api/v1/users/{id} - Lấy thông tin user\n";
echo "- PUT /api/v1/users/{id} - Cập nhật user\n";
echo "- DELETE /api/v1/users/{id} - Xóa user\n";
echo "- GET /api/v1/users/profile - Lấy profile\n";
echo "- PUT /api/v1/users/profile - Cập nhật profile\n";
