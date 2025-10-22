<?php declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

/**
 * E2E Database Seeder
 * 
 * Tạo dữ liệu chuẩn cho E2E testing
 * Bao gồm: ZENA tenant, TTF tenant + 5 user mẫu (owner/admin/pm/dev/guest)
 * 
 * @package Database\Seeders
 */
class E2EDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * @return void
     */
    public function run(): void
    {
        $this->command->info('🌱 Starting E2E Database Seeding...');

        // 1. Tạo Tenants
        $this->createTenants();
        
        // 2. Tạo Roles và Permissions
        $this->createRolesAndPermissions();
        
        // 3. Tạo Users với roles cụ thể
        $this->createUsers();
        
        // 4. Tạo dữ liệu test cơ bản
        $this->createTestData();

        $this->command->info('✅ E2E Database Seeding completed successfully!');
    }

    /**
     * Tạo tenants cho E2E testing
     */
    private function createTenants(): void
    {
        $this->command->info('📊 Creating tenants...');

        // ZENA Company - Tenant chính
        $zenaTenant = Tenant::firstOrCreate([
            'domain' => 'zena.local'
        ], [
            'name' => 'ZENA Company',
            'slug' => 'zena-company',
            'is_active' => true,
            'status' => 'active',
            'settings' => [
                'timezone' => 'Asia/Ho_Chi_Minh',
                'currency' => 'VND',
                'language' => 'vi'
            ]
        ]);

        // TTF Company - Tenant thứ hai
        $ttfTenant = Tenant::firstOrCreate([
            'domain' => 'ttf.local'
        ], [
            'name' => 'TTF Company',
            'slug' => 'ttf-company',
            'is_active' => true,
            'status' => 'active',
            'settings' => [
                'timezone' => 'Asia/Ho_Chi_Minh',
                'currency' => 'VND',
                'language' => 'vi'
            ]
        ]);

        $this->command->info("✅ Created tenants: {$zenaTenant->name}, {$ttfTenant->name}");
    }

    /**
     * Tạo roles và permissions
     */
    private function createRolesAndPermissions(): void
    {
        $this->command->info('🔐 Creating roles and permissions...');

        // Tạo roles theo schema thực tế của zena_roles
        $roles = [
            [
                'id' => 'role_owner',
                'name' => 'Owner',
                'scope' => 'system',
                'description' => 'Tenant owner with full access',
                'is_active' => true
            ],
            [
                'id' => 'role_admin',
                'name' => 'Admin',
                'scope' => 'system',
                'description' => 'System administrator',
                'is_active' => true
            ],
            [
                'id' => 'role_pm',
                'name' => 'Project Manager',
                'scope' => 'system',
                'description' => 'Project manager',
                'is_active' => true
            ],
            [
                'id' => 'role_dev',
                'name' => 'Developer',
                'scope' => 'system',
                'description' => 'Developer',
                'is_active' => true
            ],
            [
                'id' => 'role_guest',
                'name' => 'Guest',
                'scope' => 'system',
                'description' => 'Guest user with limited access',
                'is_active' => true
            ]
        ];

        // Insert roles vào bảng zena_roles
        foreach ($roles as $role) {
            DB::table('zena_roles')->updateOrInsert(
                ['id' => $role['id']],
                array_merge($role, [
                    'created_at' => now(),
                    'updated_at' => now()
                ])
            );
        }

        // Tạo permissions theo schema thực tế
        $permissions = [
            // Project permissions
            ['id' => 'perm_projects_create', 'code' => 'projects.create', 'module' => 'projects', 'action' => 'create', 'description' => 'Create Projects'],
            ['id' => 'perm_projects_read', 'code' => 'projects.read', 'module' => 'projects', 'action' => 'read', 'description' => 'Read Projects'],
            ['id' => 'perm_projects_update', 'code' => 'projects.update', 'module' => 'projects', 'action' => 'update', 'description' => 'Update Projects'],
            ['id' => 'perm_projects_delete', 'code' => 'projects.delete', 'module' => 'projects', 'action' => 'delete', 'description' => 'Delete Projects'],
            
            // Task permissions
            ['id' => 'perm_tasks_create', 'code' => 'tasks.create', 'module' => 'tasks', 'action' => 'create', 'description' => 'Create Tasks'],
            ['id' => 'perm_tasks_read', 'code' => 'tasks.read', 'module' => 'tasks', 'action' => 'read', 'description' => 'Read Tasks'],
            ['id' => 'perm_tasks_update', 'code' => 'tasks.update', 'module' => 'tasks', 'action' => 'update', 'description' => 'Update Tasks'],
            ['id' => 'perm_tasks_delete', 'code' => 'tasks.delete', 'module' => 'tasks', 'action' => 'delete', 'description' => 'Delete Tasks'],
            
            // Document permissions
            ['id' => 'perm_documents_create', 'code' => 'documents.create', 'module' => 'documents', 'action' => 'create', 'description' => 'Create Documents'],
            ['id' => 'perm_documents_read', 'code' => 'documents.read', 'module' => 'documents', 'action' => 'read', 'description' => 'Read Documents'],
            ['id' => 'perm_documents_update', 'code' => 'documents.update', 'module' => 'documents', 'action' => 'update', 'description' => 'Update Documents'],
            ['id' => 'perm_documents_delete', 'code' => 'documents.delete', 'module' => 'documents', 'action' => 'delete', 'description' => 'Delete Documents'],
            
            // User permissions
            ['id' => 'perm_users_create', 'code' => 'users.create', 'module' => 'users', 'action' => 'create', 'description' => 'Create Users'],
            ['id' => 'perm_users_read', 'code' => 'users.read', 'module' => 'users', 'action' => 'read', 'description' => 'Read Users'],
            ['id' => 'perm_users_update', 'code' => 'users.update', 'module' => 'users', 'action' => 'update', 'description' => 'Update Users'],
            ['id' => 'perm_users_delete', 'code' => 'users.delete', 'module' => 'users', 'action' => 'delete', 'description' => 'Delete Users'],
            
            // Team permissions
            ['id' => 'perm_teams_create', 'code' => 'teams.create', 'module' => 'teams', 'action' => 'create', 'description' => 'Create Teams'],
            ['id' => 'perm_teams_read', 'code' => 'teams.read', 'module' => 'teams', 'action' => 'read', 'description' => 'Read Teams'],
            ['id' => 'perm_teams_update', 'code' => 'teams.update', 'module' => 'teams', 'action' => 'update', 'description' => 'Update Teams'],
            ['id' => 'perm_teams_delete', 'code' => 'teams.delete', 'module' => 'teams', 'action' => 'delete', 'description' => 'Delete Teams'],
            
            // Settings permissions
            ['id' => 'perm_settings_read', 'code' => 'settings.read', 'module' => 'settings', 'action' => 'read', 'description' => 'Read Settings'],
            ['id' => 'perm_settings_update', 'code' => 'settings.update', 'module' => 'settings', 'action' => 'update', 'description' => 'Update Settings'],
            
            // Report permissions
            ['id' => 'perm_reports_read', 'code' => 'reports.read', 'module' => 'reports', 'action' => 'read', 'description' => 'Read Reports'],
            ['id' => 'perm_reports_create', 'code' => 'reports.create', 'module' => 'reports', 'action' => 'create', 'description' => 'Create Reports'],
            
            // Admin permissions
            ['id' => 'perm_admin_access', 'code' => 'admin.access', 'module' => 'admin', 'action' => 'access', 'description' => 'Admin Access']
        ];

        // Insert permissions
        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['id' => $permission['id']],
                array_merge($permission, [
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now()
                ])
            );
        }

        $this->command->info('✅ Roles and permissions created');
    }

    /**
     * Tạo users với roles cụ thể
     */
    private function createUsers(): void
    {
        $this->command->info('👥 Creating users...');

        $zenaTenant = Tenant::where('domain', 'zena.local')->first();
        $ttfTenant = Tenant::where('domain', 'ttf.local')->first();

        // ZENA Company Users
        $zenaUsers = [
            [
                'email' => 'owner@zena.local',
                'name' => 'ZENA Owner',
                'role' => 'super_admin',
                'password' => 'password'
            ],
            [
                'email' => 'admin@zena.local',
                'name' => 'ZENA Admin',
                'role' => 'admin',
                'password' => 'password'
            ],
            [
                'email' => 'pm@zena.local',
                'name' => 'ZENA PM',
                'role' => 'project_manager',
                'password' => 'password'
            ],
            [
                'email' => 'dev@zena.local',
                'name' => 'ZENA Dev',
                'role' => 'member',
                'password' => 'password'
            ],
            [
                'email' => 'guest@zena.local',
                'name' => 'ZENA Guest',
                'role' => 'client',
                'password' => 'password'
            ]
        ];

        // TTF Company Users
        $ttfUsers = [
            [
                'email' => 'owner@ttf.local',
                'name' => 'TTF Owner',
                'role' => 'super_admin',
                'password' => 'password'
            ],
            [
                'email' => 'admin@ttf.local',
                'name' => 'TTF Admin',
                'role' => 'admin',
                'password' => 'password'
            ],
            [
                'email' => 'pm@ttf.local',
                'name' => 'TTF PM',
                'role' => 'project_manager',
                'password' => 'password'
            ],
            [
                'email' => 'dev@ttf.local',
                'name' => 'TTF Dev',
                'role' => 'member',
                'password' => 'password'
            ],
            [
                'email' => 'guest@ttf.local',
                'name' => 'TTF Guest',
                'role' => 'client',
                'password' => 'password'
            ]
        ];

        // Tạo ZENA users
        foreach ($zenaUsers as $userData) {
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => Hash::make($userData['password']),
                    'tenant_id' => $zenaTenant->id,
                    'role' => $userData['role'],
                    'is_active' => true
                ]
            );

            // Update role if user already exists
            if ($user->wasRecentlyCreated === false) {
                $user->update(['role' => $userData['role']]);
            }
        }

        // Tạo TTF users
        foreach ($ttfUsers as $userData) {
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => Hash::make($userData['password']),
                    'tenant_id' => $ttfTenant->id,
                    'role' => $userData['role'],
                    'is_active' => true
                ]
            );

            // Update role if user already exists
            if ($user->wasRecentlyCreated === false) {
                $user->update(['role' => $userData['role']]);
            }
        }

        $this->command->info('✅ Users created: 5 ZENA users + 5 TTF users');
    }

    /**
     * Tạo dữ liệu test cơ bản
     */
    private function createTestData(): void
    {
        $this->command->info('📊 Creating test data...');

        // Tạo một số projects mẫu cho ZENA tenant
        $zenaTenant = Tenant::where('domain', 'zena.local')->first();
        $zenaOwner = User::where('email', 'owner@zena.local')->first();
        
        // Tạo projects mẫu với schema đúng
        $projects = [
            [
                'id' => \Illuminate\Support\Str::ulid(),
                'tenant_id' => $zenaTenant->id,
                'code' => 'E2E-001',
                'name' => 'E2E Test Project 1',
                'description' => 'Project for E2E testing - Basic functionality',
                'status' => 'active',
                'priority' => 'medium',
                'progress' => 25.00,
                'progress_pct' => 25,
                'budget_total' => 50000.00,
                'start_date' => now()->subDays(30)->format('Y-m-d'),
                'end_date' => now()->addDays(60)->format('Y-m-d'),
                'owner_id' => $zenaOwner->id,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => \Illuminate\Support\Str::ulid(),
                'tenant_id' => $zenaTenant->id,
                'code' => 'E2E-002',
                'name' => 'E2E Test Project 2',
                'description' => 'Project for E2E testing - Advanced features',
                'status' => 'planning',
                'priority' => 'high',
                'progress' => 0.00,
                'progress_pct' => 0,
                'budget_total' => 75000.00,
                'start_date' => now()->addDays(7)->format('Y-m-d'),
                'end_date' => now()->addDays(90)->format('Y-m-d'),
                'owner_id' => $zenaOwner->id,
                'created_at' => now(),
                'updated_at' => now()
            ]
        ];

        foreach ($projects as $project) {
            DB::table('projects')->updateOrInsert(
                ['code' => $project['code']],
                $project
            );
        }

        // Tạo tasks mẫu cho các projects
        $this->createTaskData($zenaTenant, $zenaOwner);

        $this->command->info('✅ Test data created: 2 sample projects + tasks');
    }

    /**
     * Tạo task data cho E2E testing
     */
    private function createTaskData($tenant, $owner): void
    {
        $this->command->info('📋 Creating task data...');

        // Lấy project IDs
        $project1 = DB::table('projects')->where('code', 'E2E-001')->first();
        $project2 = DB::table('projects')->where('code', 'E2E-002')->first();
        
        if (!$project1 || !$project2) {
            $this->command->warn('⚠️ Projects not found - skipping task creation');
            return;
        }

        // Lấy users cho assignment
        $adminUser = User::where('email', 'admin@zena.local')->first();
        $pmUser = User::where('email', 'pm@zena.local')->first();
        $devUser = User::where('email', 'dev@zena.local')->first();

        // Tạo tasks cho Project 1 (E2E-001)
        $tasksProject1 = [
            [
                'id' => \Illuminate\Support\Str::ulid(),
                'tenant_id' => $tenant->id,
                'project_id' => $project1->id,
                'name' => 'Setup Development Environment',
                'description' => 'Configure development environment and tools',
                'status' => 'completed',
                'priority' => 'high',
                'progress_percent' => 100,
                'estimated_hours' => 8.0,
                'actual_hours' => 8.5,
                'start_date' => now()->subDays(25)->format('Y-m-d'),
                'end_date' => now()->subDays(20)->format('Y-m-d'),
                'assignee_id' => $devUser->id,
                'created_at' => now()->subDays(30),
                'updated_at' => now()->subDays(20)
            ],
            [
                'id' => \Illuminate\Support\Str::ulid(),
                'tenant_id' => $tenant->id,
                'project_id' => $project1->id,
                'name' => 'Design Database Schema',
                'description' => 'Create database schema and relationships',
                'status' => 'in_progress',
                'priority' => 'high',
                'progress_percent' => 60,
                'estimated_hours' => 16.0,
                'actual_hours' => 10.0,
                'start_date' => now()->subDays(15)->format('Y-m-d'),
                'end_date' => now()->addDays(5)->format('Y-m-d'),
                'assignee_id' => $devUser->id,
                'created_at' => now()->subDays(20),
                'updated_at' => now()->subDays(2)
            ],
            [
                'id' => \Illuminate\Support\Str::ulid(),
                'tenant_id' => $tenant->id,
                'project_id' => $project1->id,
                'name' => 'Implement User Authentication',
                'description' => 'Build user login and registration system',
                'status' => 'todo',
                'priority' => 'medium',
                'progress_percent' => 0,
                'estimated_hours' => 24.0,
                'actual_hours' => 0.0,
                'start_date' => now()->addDays(3)->format('Y-m-d'),
                'end_date' => now()->addDays(10)->format('Y-m-d'),
                'assignee_id' => $devUser->id,
                'created_at' => now()->subDays(10),
                'updated_at' => now()->subDays(10)
            ]
        ];

        // Tạo tasks cho Project 2 (E2E-002)
        $tasksProject2 = [
            [
                'id' => \Illuminate\Support\Str::ulid(),
                'tenant_id' => $tenant->id,
                'project_id' => $project2->id,
                'name' => 'Project Planning and Requirements',
                'description' => 'Define project scope and requirements',
                'status' => 'completed',
                'priority' => 'high',
                'progress_percent' => 100,
                'estimated_hours' => 12.0,
                'actual_hours' => 14.0,
                'start_date' => now()->subDays(10)->format('Y-m-d'),
                'end_date' => now()->subDays(5)->format('Y-m-d'),
                'assignee_id' => $pmUser->id,
                'created_at' => now()->subDays(15),
                'updated_at' => now()->subDays(5)
            ],
            [
                'id' => \Illuminate\Support\Str::ulid(),
                'tenant_id' => $tenant->id,
                'project_id' => $project2->id,
                'name' => 'UI/UX Design',
                'description' => 'Create wireframes and design mockups',
                'status' => 'in_progress',
                'priority' => 'medium',
                'progress_percent' => 30,
                'estimated_hours' => 20.0,
                'actual_hours' => 6.0,
                'start_date' => now()->subDays(3)->format('Y-m-d'),
                'end_date' => now()->addDays(12)->format('Y-m-d'),
                'assignee_id' => $devUser->id,
                'created_at' => now()->subDays(8),
                'updated_at' => now()->subDays(1)
            ],
            [
                'id' => \Illuminate\Support\Str::ulid(),
                'tenant_id' => $tenant->id,
                'project_id' => $project2->id,
                'name' => 'API Development',
                'description' => 'Build REST API endpoints',
                'status' => 'todo',
                'priority' => 'high',
                'progress_percent' => 0,
                'estimated_hours' => 32.0,
                'actual_hours' => 0.0,
                'start_date' => now()->addDays(7)->format('Y-m-d'),
                'end_date' => now()->addDays(20)->format('Y-m-d'),
                'assignee_id' => $devUser->id,
                'created_at' => now()->subDays(5),
                'updated_at' => now()->subDays(5)
            ]
        ];

        // Insert tasks
        $allTasks = array_merge($tasksProject1, $tasksProject2);
        
        foreach ($allTasks as $task) {
            DB::table('tasks')->updateOrInsert(
                ['id' => $task['id']],
                $task
            );
        }

        $this->command->info("✅ Created " . count($allTasks) . " tasks across 2 projects");
    }
}
