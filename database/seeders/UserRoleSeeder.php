<?php declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * User Role Assignment Seeder
 * 
 * Gán roles cho users
 * Sử dụng ULID cho tất cả foreign keys
 */
class UserRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminUser = User::where('email', 'admin@zena.local')->first();
        $adminRole = Role::where('name', 'System Admin')->first();
        
        if ($adminUser && $adminRole) {
            // Gán system admin role cho admin user bằng updateOrInsert()
            DB::table('user_roles')->updateOrInsert(
                [
                    'user_id' => $adminUser->id,
                    'role_id' => $adminRole->id
                ],
                [
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            );
        }

        // Gán roles cho các users khác
        $otherUsers = User::where('email', '!=', 'admin@zena.local')->take(5)->get();
        $memberRole = Role::where('name', 'Project Member')->first();
        
        if ($memberRole) {
            foreach ($otherUsers as $user) {
                DB::table('user_roles')->updateOrInsert(
                    [
                        'user_id' => $user->id,
                        'role_id' => $memberRole->id
                    ],
                    [
                        'created_at' => now(),
                        'updated_at' => now()
                    ]
                );
            }
        }
    }
}