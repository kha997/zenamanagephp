<?php declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
            $this->ensureUserRole([
                'user_id' => $adminUser->id,
                'role_id' => $adminRole->id,
            ]);
        }

        // Gán roles cho các users khác
        $otherUsers = User::where('email', '!=', 'admin@zena.local')->take(5)->get();
        $memberRole = Role::where('name', 'Project Member')->first();
        
        if ($memberRole) {
            foreach ($otherUsers as $user) {
                $this->ensureUserRole([
                    'user_id' => $user->id,
                    'role_id' => $memberRole->id,
                ]);
            }
        }
    }

    private function ensureUserRole(array $attributes): void
    {
        $recordExists = DB::table('user_roles')->where($attributes)->exists();

        if ($recordExists) {
            DB::table('user_roles')
                ->where($attributes)
                ->update(['updated_at' => now()]);

            return;
        }

        DB::table('user_roles')->insert([
            'id' => (string) Str::ulid(),
            'created_at' => now(),
            'updated_at' => now(),
            ...$attributes,
        ]);
    }
}
