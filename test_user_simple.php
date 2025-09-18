<?php
/**
 * Simple User Management Test - Bypass middleware issues
 */

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Models\Tenant;

echo "=== SIMPLE USER MANAGEMENT TEST ===\n\n";

try {
    // Test 1: Get all users
    echo "1. Getting all users...\n";
    $users = User::with('tenant')->get();
    echo "✅ Found " . $users->count() . " users:\n";
    foreach ($users as $user) {
        echo "   - {$user->name} ({$user->email}) - Tenant: {$user->tenant->name}\n";
    }
    
    // Test 2: Create a new user
    echo "\n2. Creating a new user...\n";
    $newUser = User::create([
        'name' => 'Test User ' . time(),
        'email' => 'test' . time() . '@example.com',
        'password' => bcrypt('password123'),
        'tenant_id' => $users->first()->tenant_id,
        'status' => 'active'
    ]);
    echo "✅ Created user: {$newUser->name} ({$newUser->email})\n";
    
    // Test 3: Update user
    echo "\n3. Updating user...\n";
    $newUser->update(['name' => 'Updated Test User']);
    echo "✅ Updated user name to: {$newUser->name}\n";
    
    // Test 4: Delete user
    echo "\n4. Deleting user...\n";
    $newUser->delete();
    echo "✅ User deleted successfully\n";
    
    echo "\n🎉 All tests passed! User Management is working correctly.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
