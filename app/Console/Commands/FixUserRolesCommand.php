<?php declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Role;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Fix User Roles Command
 * 
 * Kiểm tra và fix tất cả users thiếu role trong database.
 * 
 * Logic:
 * - Kiểm tra users có role trong field `role` nhưng chưa có trong relationship `roles()`
 * - Sync role từ field sang relationship
 * - Báo cáo users không có role nào cả
 */
class FixUserRolesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:fix-roles 
                            {--dry-run : Chạy ở chế độ dry-run, không thực hiện thay đổi}
                            {--default-role=member : Role mặc định cho users không có role}
                            {--auto-map : Tự động map role dựa trên email pattern}
                            {--force : Bỏ qua confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Kiểm tra và fix tất cả users thiếu role trong database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $defaultRole = $this->option('default-role');
        $force = $this->option('force');

        $this->info('🔍 Đang kiểm tra users thiếu role...');
        $this->newLine();

        // Get all active users
        $users = User::where('is_active', true)->get();
        $totalUsers = $users->count();

        $this->info("📊 Tổng số users: {$totalUsers}");
        $this->newLine();

        // Statistics
        $stats = [
            'total' => $totalUsers,
            'has_role_field' => 0,
            'has_role_relationship' => 0,
            'missing_both' => 0,
            'missing_relationship' => 0,
            'fixed' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        $usersToFix = [];
        $usersWithoutRole = [];

        // Check each user
        foreach ($users as $user) {
            $hasRoleField = !empty($user->role);
            $hasRoleRelationship = $user->roles()->exists();
            $roleNames = $user->roles()->pluck('name')->toArray();

            if ($hasRoleField) {
                $stats['has_role_field']++;
            }

            if ($hasRoleRelationship) {
                $stats['has_role_relationship']++;
            }

            // Case 1: Có role field nhưng không có relationship
            if ($hasRoleField && !$hasRoleRelationship) {
                $usersToFix[] = [
                    'user' => $user,
                    'issue' => 'missing_relationship',
                    'role_field' => $user->role,
                    'current_roles' => [],
                ];
                $stats['missing_relationship']++;
            }
            // Case 2: Không có cả 2
            elseif (!$hasRoleField && !$hasRoleRelationship) {
                $usersWithoutRole[] = [
                    'user' => $user,
                    'issue' => 'missing_both',
                    'role_field' => null,
                    'current_roles' => [],
                ];
                $stats['missing_both']++;
            }
            // Case 3: Có relationship nhưng role field khác (có thể sync ngược lại)
            elseif ($hasRoleRelationship && $hasRoleField && !in_array($user->role, $roleNames)) {
                // Role field không khớp với relationship - có thể cần sync
                $this->warn("⚠️  User {$user->email} có role field '{$user->role}' nhưng relationship có: " . implode(', ', $roleNames));
            }
        }

        // Display statistics
        $this->displayStatistics($stats);

        // Display users to fix
        if (!empty($usersToFix)) {
            $this->newLine();
            $this->info('📝 Users cần fix (có role field nhưng thiếu relationship):');
            $this->newLine();

            $tableData = [];
            foreach ($usersToFix as $item) {
                $tableData[] = [
                    'ID' => $item['user']->id,
                    'Email' => $item['user']->email,
                    'Name' => $item['user']->name,
                    'Role Field' => $item['role_field'] ?? 'null',
                    'Current Roles' => implode(', ', $item['current_roles']) ?: 'none',
                ];
            }

            $this->table(
                ['ID', 'Email', 'Name', 'Role Field', 'Current Roles'],
                $tableData
            );
        }

        // Display users without any role
        if (!empty($usersWithoutRole)) {
            $this->newLine();
            $this->warn('⚠️  Users không có role nào cả:');
            $this->newLine();

            $tableData = [];
            foreach ($usersWithoutRole as $item) {
                $tableData[] = [
                    'ID' => $item['user']->id,
                    'Email' => $item['user']->email,
                    'Name' => $item['user']->name,
                ];
            }

            $this->table(
                ['ID', 'Email', 'Name'],
                $tableData
            );
        }

        // Ask for confirmation if not dry-run and not forced
        if (!$dryRun && !$force) {
            $totalToFix = count($usersToFix) + count($usersWithoutRole);
            if ($totalToFix > 0) {
                if (!$this->confirm("Bạn có muốn fix {$totalToFix} users? (yes/no)")) {
                    $this->info('❌ Đã hủy.');
                    return Command::FAILURE;
                }
            } else {
                $this->info('✅ Không có users nào cần fix.');
                return Command::SUCCESS;
            }
        }

        // Fix users
        if ($dryRun) {
            $this->newLine();
            $this->info('🔍 DRY-RUN MODE: Không thực hiện thay đổi.');
            return Command::SUCCESS;
        }

        $this->newLine();
        $this->info('🔧 Đang fix users...');
        $this->newLine();

        // Fix users with role field but missing relationship
        foreach ($usersToFix as $item) {
            $user = $item['user'];
            $roleName = $item['role_field'];

            try {
                $role = Role::where('name', $roleName)->first();

                if (!$role) {
                    $this->warn("⚠️  Role '{$roleName}' không tồn tại cho user {$user->email}");
                    $stats['errors']++;
                    $stats['skipped']++;
                    continue;
                }

                // Assign role to user
                $user->roles()->syncWithoutDetaching([$role->id]);

                $this->info("✅ Đã assign role '{$roleName}' cho user {$user->email}");
                $stats['fixed']++;

                Log::info('User role fixed by command', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'role' => $roleName,
                    'command' => 'users:fix-roles',
                ]);
            } catch (\Exception $e) {
                $this->error("❌ Lỗi khi fix user {$user->email}: {$e->getMessage()}");
                $stats['errors']++;
                $stats['skipped']++;

                Log::error('Failed to fix user role', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'role' => $roleName,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Fix users without any role
        if (!empty($usersWithoutRole)) {
            $autoMap = $this->option('auto-map');

            foreach ($usersWithoutRole as $item) {
                $user = $item['user'];
                $roleToAssign = null;

                // Try to auto-map role based on email pattern
                if ($autoMap) {
                    $roleToAssign = $this->mapRoleFromEmail($user->email);
                }

                // Fallback to default role if auto-map didn't find a role
                if (!$roleToAssign) {
                    $roleToAssign = $defaultRole;
                }

                $roleModel = Role::where('name', $roleToAssign)->first();

                if (!$roleModel) {
                    $this->warn("⚠️  Role '{$roleToAssign}' không tồn tại cho user {$user->email}");
                    $stats['errors']++;
                    $stats['skipped']++;
                    continue;
                }

                try {
                    // Assign role
                    $user->roles()->syncWithoutDetaching([$roleModel->id]);

                    $this->info("✅ Đã assign role '{$roleToAssign}' cho user {$user->email}");
                    $stats['fixed']++;

                    Log::info('User assigned role by command', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'role' => $roleToAssign,
                        'auto_mapped' => $autoMap && $roleToAssign !== $defaultRole,
                        'command' => 'users:fix-roles',
                    ]);
                } catch (\Exception $e) {
                    $this->error("❌ Lỗi khi assign role cho user {$user->email}: {$e->getMessage()}");
                    $stats['errors']++;
                    $stats['skipped']++;

                    Log::error('Failed to assign role', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'role' => $roleToAssign,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // Final summary
        $this->newLine();
        $this->info('📊 Kết quả:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Users', $stats['total']],
                ['Has Role Field', $stats['has_role_field']],
                ['Has Role Relationship', $stats['has_role_relationship']],
                ['Missing Relationship', $stats['missing_relationship']],
                ['Missing Both', $stats['missing_both']],
                ['Fixed', $stats['fixed']],
                ['Skipped', $stats['skipped']],
                ['Errors', $stats['errors']],
            ]
        );

        if ($stats['fixed'] > 0) {
            $this->info("✅ Đã fix thành công {$stats['fixed']} users.");
        }

        if ($stats['errors'] > 0) {
            $this->warn("⚠️  Có {$stats['errors']} lỗi xảy ra.");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Display statistics
     */
    private function displayStatistics(array $stats): void
    {
        $this->info('📊 Thống kê:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Users', $stats['total']],
                ['Có Role Field', $stats['has_role_field']],
                ['Có Role Relationship', $stats['has_role_relationship']],
                ['Thiếu Relationship', $stats['missing_relationship']],
                ['Thiếu Cả 2', $stats['missing_both']],
            ]
        );
    }

    /**
     * Map role from email pattern
     * 
     * @param string $email
     * @return string|null
     */
    private function mapRoleFromEmail(string $email): ?string
    {
        // Extract username from email (before @)
        $username = strtolower(explode('@', $email)[0]);

        // Map common email patterns to roles
        $roleMapping = [
            'superadmin' => 'super_admin',
            'super_admin' => 'super_admin',
            'admin' => 'admin',
            'administrator' => 'admin',
            'pm' => 'project_manager',
            'projectmanager' => 'project_manager',
            'project_manager' => 'project_manager',
            'designer' => 'designer',
            'site' => 'site_engineer',
            'siteengineer' => 'site_engineer',
            'site_engineer' => 'site_engineer',
            'qc' => 'qc_engineer',
            'qcengineer' => 'qc_engineer',
            'qc_engineer' => 'qc_engineer',
            'procurement' => 'procurement',
            'finance' => 'finance',
            'client' => 'client',
            'member' => 'member',
            'user' => 'member',
        ];

        // Check exact match first
        if (isset($roleMapping[$username])) {
            return $roleMapping[$username];
        }

        // Check partial match
        foreach ($roleMapping as $pattern => $role) {
            if (str_contains($username, $pattern)) {
                return $role;
            }
        }

        return null;
    }
}

