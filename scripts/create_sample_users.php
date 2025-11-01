<?php

/**
 * Script tạo users mẫu cho testing
 */

require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "👥 TẠO USERS MẪU CHO TESTING\n";
echo "============================\n\n";

// Tạo users mẫu
$users = [
    [
        'name' => 'John Smith',
        'email' => 'john.smith@zenamanage.com',
        'password' => Hash::make('password123'),
        'email_verified_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ],
    [
        'name' => 'Sarah Wilson',
        'email' => 'sarah.wilson@zenamanage.com',
        'password' => Hash::make('password123'),
        'email_verified_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ],
    [
        'name' => 'Mike Johnson',
        'email' => 'mike.johnson@zenamanage.com',
        'password' => Hash::make('password123'),
        'email_verified_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ],
    [
        'name' => 'Alex Lee',
        'email' => 'alex.lee@zenamanage.com',
        'password' => Hash::make('password123'),
        'email_verified_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ],
    [
        'name' => 'Emily Davis',
        'email' => 'emily.davis@zenamanage.com',
        'password' => Hash::make('password123'),
        'email_verified_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ],
];

try {
    // Kiểm tra xem có users nào chưa
    $existingUsers = \App\Models\User::count();
    echo "📊 Existing users: {$existingUsers}\n";
    
    if ($existingUsers == 0) {
        echo "👤 Creating sample users...\n";
        
        foreach ($users as $userData) {
            $user = \App\Models\User::create($userData);
            echo "  ✅ Created: {$user->name} ({$user->email})\n";
        }
        
        echo "\n🎯 Created " . count($users) . " sample users successfully!\n";
    } else {
        echo "⚠️ Users already exist, skipping creation.\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n🎉 Hoàn thành!\n";
