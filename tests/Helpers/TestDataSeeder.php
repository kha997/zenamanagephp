<?php declare(strict_types=1);

namespace Tests\Helpers;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Support\Facades\Hash;

/**
 * TestDataSeeder - Standardize test data creation
 * 
 * Provides consistent test data seeding for all test suites
 * Ensures test users and tenants are created with standardized attributes
 * 
 * @package Tests\Helpers
 */
class TestDataSeeder
{
    /**
     * Standard test tenant attributes
     * 
     * @var array
     */
    public static array $defaultTenantAttributes = [
        'name' => 'Test Tenant',
        'slug' => 'test-tenant',
        'status' => 'active',
        'is_active' => true,
    ];

    /**
     * Standard test user attributes
     * 
     * @var array
     */
    public static array $defaultUserAttributes = [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password', // Will be hashed
        'email_verified_at' => null, // Will be set to now() if not specified
        'is_active' => true,
        'role' => 'member',
    ];

    /**
     * Create a standard test tenant
     * 
     * @param array $attributes Override default attributes
     * @return Tenant
     */
    public static function createTenant(array $attributes = []): Tenant
    {
        $tenantAttributes = array_merge(self::$defaultTenantAttributes, $attributes);
        
        // Ensure unique slug
        if (!isset($attributes['slug'])) {
            $tenantAttributes['slug'] = $tenantAttributes['slug'] . '-' . uniqid();
        }

        return Tenant::factory()->create($tenantAttributes);
    }

    /**
     * Create a standard test user
     * 
     * @param Tenant|null $tenant Tenant instance (will create one if not provided)
     * @param array $attributes Override default attributes
     * @return User
     */
    public static function createUser(?Tenant $tenant = null, array $attributes = []): User
    {
        if (!$tenant) {
            $tenant = self::createTenant();
        }

        $userAttributes = array_merge(self::$defaultUserAttributes, $attributes);
        
        // Hash password if provided as plain text
        if (isset($userAttributes['password']) && !str_starts_with($userAttributes['password'], '$2y$')) {
            $userAttributes['password'] = Hash::make($userAttributes['password']);
        }

        // Set email_verified_at to now() if not explicitly set to null
        if (!isset($attributes['email_verified_at']) && $userAttributes['email_verified_at'] === null) {
            $userAttributes['email_verified_at'] = now();
        }

        // Ensure unique email
        if (!isset($attributes['email'])) {
            $userAttributes['email'] = 'test-' . uniqid() . '@example.com';
        }

        $userAttributes['tenant_id'] = $tenant->id;

        return User::factory()->create($userAttributes);
    }

    /**
     * Create a test user with a specific role
     * 
     * @param string $role User role
     * @param Tenant|null $tenant Tenant instance (will create one if not provided)
     * @param array $attributes Override default attributes
     * @return User
     */
    public static function createUserWithRole(
        string $role,
        ?Tenant $tenant = null,
        array $attributes = []
    ): User {
        $attributes['role'] = $role;
        return self::createUser($tenant, $attributes);
    }

    /**
     * Create multiple test users with different roles
     * 
     * @param array $roles Array of roles to create users for
     * @param Tenant|null $tenant Tenant instance (will create one if not provided)
     * @return array Array of User instances indexed by role
     */
    public static function createUsersWithRoles(array $roles, ?Tenant $tenant = null): array
    {
        if (!$tenant) {
            $tenant = self::createTenant();
        }

        $users = [];
        foreach ($roles as $role) {
            $users[$role] = self::createUserWithRole($role, $tenant);
        }

        return $users;
    }

    /**
     * Create a complete test setup: tenant + admin user + regular user
     * 
     * @param array $tenantAttributes Tenant attributes
     * @return array ['tenant' => Tenant, 'admin' => User, 'user' => User]
     */
    public static function createCompleteTestSetup(array $tenantAttributes = []): array
    {
        $tenant = self::createTenant($tenantAttributes);
        
        $admin = self::createUserWithRole('admin', $tenant, [
            'email' => 'admin@test.com',
            'name' => 'Admin User',
        ]);

        $user = self::createUser($tenant, [
            'email' => 'user@test.com',
            'name' => 'Regular User',
            'role' => 'member',
        ]);

        return [
            'tenant' => $tenant,
            'admin' => $admin,
            'user' => $user,
        ];
    }

    /**
     * Create a test user with verified email
     * 
     * @param Tenant|null $tenant Tenant instance
     * @param array $attributes Override default attributes
     * @return User
     */
    public static function createVerifiedUser(?Tenant $tenant = null, array $attributes = []): User
    {
        $attributes['email_verified_at'] = now();
        return self::createUser($tenant, $attributes);
    }

    /**
     * Create a test user with unverified email
     * 
     * @param Tenant|null $tenant Tenant instance
     * @param array $attributes Override default attributes
     * @return User
     */
    public static function createUnverifiedUser(?Tenant $tenant = null, array $attributes = []): User
    {
        $attributes['email_verified_at'] = null;
        return self::createUser($tenant, $attributes);
    }

    /**
     * Create a test user with inactive status
     * 
     * @param Tenant|null $tenant Tenant instance
     * @param array $attributes Override default attributes
     * @return User
     */
    public static function createInactiveUser(?Tenant $tenant = null, array $attributes = []): User
    {
        $attributes['is_active'] = false;
        return self::createUser($tenant, $attributes);
    }

    /**
     * Seed authentication domain test data with fixed seed for reproducibility
     * 
     * This method creates a complete auth domain test setup including:
     * - Tenant
     * - Roles (admin, member, client, project_manager)
     * - Permissions (auth-related permissions)
     * - Users with different roles
     * - Role-permission assignments
     * - User-role assignments
     * 
     * TODO for Continue Agent: Implement the method body following the template in
     * docs/work-packages/auth-domain-helper-guide.md (Phase 3).
     * 
     * @param int $seed Fixed seed value (default: 12345)
     * @return array{
     *     tenant: \App\Models\Tenant,
     *     users: \App\Models\User[],
     *     roles: \App\Models\Role[],
     *     permissions: \App\Models\Permission[]
     * }
     */
    public static function seedAuthDomain(int $seed = 12345): array
    {
        // Set fixed seed for reproducibility
        mt_srand($seed);
        
        // Create tenant
        $tenant = self::createTenant([
            'name' => 'Auth Test Tenant',
            'slug' => 'auth-test-tenant-' . $seed,
            'status' => 'active',
        ]);
        
        // Create roles
        $roles = [];
        $roleNames = ['admin', 'member', 'client', 'project_manager'];
        
        foreach ($roleNames as $roleName) {
            $roles[$roleName] = \App\Models\Role::create([
                'name' => $roleName,
                'scope' => 'system',
                // Note: allow_override column does not exist in zena_roles table
                'description' => "Auth test role: {$roleName}",
                'is_active' => true,
                'tenant_id' => $tenant->id,
            ]);
        }
        
        // Create permissions (auth-related)
        $permissions = [];
        $permissionData = [
            ['module' => 'auth', 'action' => 'login', 'description' => 'Can login'],
            ['module' => 'auth', 'action' => 'logout', 'description' => 'Can logout'],
            ['module' => 'auth', 'action' => 'register', 'description' => 'Can register'],
            ['module' => 'auth', 'action' => 'reset_password', 'description' => 'Can reset password'],
            ['module' => 'auth', 'action' => 'change_password', 'description' => 'Can change password'],
            ['module' => 'auth', 'action' => 'verify_email', 'description' => 'Can verify email'],
        ];
        
        foreach ($permissionData as $permData) {
            $permissions[] = \App\Models\Permission::create([
                'code' => \App\Models\Permission::generateCode($permData['module'], $permData['action']),
                'module' => $permData['module'],
                'action' => $permData['action'],
                'description' => $permData['description'],
            ]);
        }
        
        // Attach permissions to roles
        // Admin gets all permissions
        $roles['admin']->permissions()->attach(
            collect($permissions)->pluck('id')->toArray()
        );
        
        // Member gets basic permissions
        $memberPerms = collect($permissions)
            ->whereIn('action', ['login', 'logout', 'change_password', 'verify_email'])
            ->pluck('id')
            ->toArray();
        $roles['member']->permissions()->attach($memberPerms);
        
        // Client gets limited permissions
        $clientPerms = collect($permissions)
            ->whereIn('action', ['login', 'logout', 'change_password'])
            ->pluck('id')
            ->toArray();
        $roles['client']->permissions()->attach($clientPerms);
        
        // Project manager gets most permissions
        $pmPerms = collect($permissions)
            ->whereIn('action', ['login', 'logout', 'change_password', 'verify_email', 'register'])
            ->pluck('id')
            ->toArray();
        $roles['project_manager']->permissions()->attach($pmPerms);
        
        // Create users with different roles
        $users = [];
        
        // Admin user
        $users['admin'] = self::createUser($tenant, [
            'name' => 'Auth Admin User',
            'email' => 'admin@auth-test.test',
            'password' => 'password',
            'role' => 'admin',
        ]);
        $users['admin']->roles()->attach($roles['admin']->id);
        
        // Member user
        $users['member'] = self::createUser($tenant, [
            'name' => 'Auth Member User',
            'email' => 'member@auth-test.test',
            'password' => 'password',
            'role' => 'member',
        ]);
        $users['member']->roles()->attach($roles['member']->id);
        
        // Client user
        $users['client'] = self::createUser($tenant, [
            'name' => 'Auth Client User',
            'email' => 'client@auth-test.test',
            'password' => 'password',
            'role' => 'client',
        ]);
        $users['client']->roles()->attach($roles['client']->id);
        
        // Project manager user
        $users['project_manager'] = self::createUser($tenant, [
            'name' => 'Auth PM User',
            'email' => 'pm@auth-test.test',
            'password' => 'password',
            'role' => 'project_manager',
        ]);
        $users['project_manager']->roles()->attach($roles['project_manager']->id);
        
        return [
            'tenant' => $tenant,
            'users' => array_values($users),
            'roles' => array_values($roles),
            'permissions' => $permissions,
        ];
    }

    /**
     * Seed projects domain test data with fixed seed for reproducibility
     * 
     * This method creates a complete projects domain test setup including:
     * - Tenant
     * - Users (project manager, team members, client contact, admin)
     * - Clients (active, prospect)
     * - Projects with different statuses (active, planning, on_hold)
     * - Components for projects
     * - User-project role assignments (if applicable)
     * 
     * TODO for Future Agent: Implement the method body following the template in
     * docs/work-packages/projects-domain-helper-guide.md (Phase 3).
     * 
     * @param int $seed Fixed seed value (default: 23456)
     * @return array{
     *     tenant: \App\Models\Tenant,
     *     users: \App\Models\User[],
     *     projects: \App\Models\Project[],
     *     components: \App\Models\Component[],
     *     clients: \App\Models\Client[]
     * }
     */
    public static function seedProjectsDomain(int $seed = 23456): array
    {
        // Set fixed seed for reproducibility
        mt_srand($seed);
        
        // Create tenant
        $tenant = self::createTenant([
            'name' => 'Projects Test Tenant',
            'slug' => 'projects-test-tenant-' . $seed,
            'status' => 'active',
        ]);
        
        // Create users with different roles
        $users = [];
        
        // Project manager
        $users['project_manager'] = self::createUser($tenant, [
            'name' => 'Projects PM User',
            'email' => 'pm@projects-test.test',
            'password' => 'password',
            'role' => 'project_manager',
        ]);
        
        // Team member
        $users['team_member'] = self::createUser($tenant, [
            'name' => 'Projects Team Member',
            'email' => 'member@projects-test.test',
            'password' => 'password',
            'role' => 'member',
        ]);
        
        // Client contact
        $users['client_contact'] = self::createUser($tenant, [
            'name' => 'Projects Client Contact',
            'email' => 'client@projects-test.test',
            'password' => 'password',
            'role' => 'client',
        ]);
        
        // Admin user
        $users['admin'] = self::createUser($tenant, [
            'name' => 'Projects Admin User',
            'email' => 'admin@projects-test.test',
            'password' => 'password',
            'role' => 'admin',
        ]);
        
        // Create clients
        $clients = [];
        $clients['active'] = \App\Models\Client::create([
            'tenant_id' => $tenant->id,
            'name' => 'Active Test Client',
            'email' => 'active@client.test',
            'phone' => '1234567890',
            'company' => 'Active Client Company',
            'lifecycle_stage' => 'customer',
        ]);
        
        $clients['prospect'] = \App\Models\Client::create([
            'tenant_id' => $tenant->id,
            'name' => 'Prospect Test Client',
            'email' => 'prospect@client.test',
            'phone' => '1234567891',
            'company' => 'Prospect Client Company',
            'lifecycle_stage' => 'prospect',
        ]);
        
        // Create projects with different statuses
        $projects = [];
        
        // Active project
        $projects['active'] = \App\Models\Project::create([
            'tenant_id' => $tenant->id,
            'code' => 'PROJ-' . $seed . '-001',
            'name' => 'Active Test Project',
            'description' => 'An active project for testing',
            'status' => 'active',
            'priority' => 'high',
            'owner_id' => $users['project_manager']->id,
            'client_id' => $clients['active']->id,
            'budget_total' => 100000.00,
            'budget_planned' => 80000.00,
            'budget_actual' => 0.00,
            'progress_pct' => 0,
            'estimated_hours' => 200.0,
            'actual_hours' => 0.0,
            'risk_level' => 'low',
            'start_date' => now(),
            'end_date' => now()->addMonths(6),
        ]);
        
        // Planning project
        $projects['planning'] = \App\Models\Project::create([
            'tenant_id' => $tenant->id,
            'code' => 'PROJ-' . $seed . '-002',
            'name' => 'Planning Test Project',
            'description' => 'A project in planning phase',
            'status' => 'planning',
            'priority' => 'normal',
            'owner_id' => $users['project_manager']->id,
            'client_id' => $clients['prospect']->id,
            'budget_total' => 50000.00,
            'budget_planned' => 50000.00,
            'budget_actual' => 0.00,
            'progress_pct' => 0,
            'estimated_hours' => 100.0,
            'actual_hours' => 0.0,
            'risk_level' => 'medium',
            'start_date' => now()->addMonth(),
            'end_date' => now()->addMonths(4),
        ]);
        
        // On hold project
        $projects['on_hold'] = \App\Models\Project::create([
            'tenant_id' => $tenant->id,
            'code' => 'PROJ-' . $seed . '-003',
            'name' => 'On Hold Test Project',
            'description' => 'A project on hold',
            'status' => 'on_hold',
            'priority' => 'low',
            'owner_id' => $users['project_manager']->id,
            'client_id' => $clients['active']->id,
            'budget_total' => 75000.00,
            'budget_planned' => 60000.00,
            'budget_actual' => 10000.00,
            'progress_pct' => 15,
            'estimated_hours' => 150.0,
            'actual_hours' => 20.0,
            'risk_level' => 'high',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonths(5),
        ]);
        
        // Create components for active project
        $components = [];
        $componentNames = ['Design', 'Development', 'Testing', 'Deployment'];
        
        foreach ($componentNames as $index => $componentName) {
            $components[] = \App\Models\Component::create([
                'tenant_id' => $tenant->id,
                'project_id' => $projects['active']->id,
                'name' => $componentName,
                'description' => "Component: {$componentName}",
                'type' => 'phase',
                'status' => $index === 0 ? 'active' : 'pending',
                'priority' => 'normal',
                'progress_percent' => $index === 0 ? 25.0 : 0.0,
                'planned_cost' => 20000.00,
                'actual_cost' => $index === 0 ? 5000.00 : 0.00,
            ]);
        }
        
        return [
            'tenant' => $tenant,
            'users' => array_values($users),
            'projects' => array_values($projects),
            'components' => $components,
            'clients' => array_values($clients),
        ];
    }

    /**
     * Seed tasks domain test data with fixed seed for reproducibility
     * 
     * This method creates a complete tasks domain test setup including:
     * - Tenant
     * - Users (project manager, team members)
     * - Projects (for tasks to belong to)
     * - Components (for tasks to belong to)
     * - Tasks with different statuses and priorities
     * - Task assignments (linking users to tasks)
     * - Task dependencies (linking tasks to each other)
     * 
     * TODO for Future Agent: Implement the method body following the template in
     * docs/work-packages/tasks-domain-helper-guide.md (Phase 3).
     * 
     * @param int $seed Fixed seed value (default: 34567)
     * @return array{
     *     tenant: \App\Models\Tenant,
     *     users: \App\Models\User[],
     *     projects: \App\Models\Project[],
     *     components: \App\Models\Component[],
     *     tasks: \App\Models\Task[],
     *     task_assignments: \App\Models\TaskAssignment[],
     *     task_dependencies: \App\Models\TaskDependency[]
     * }
     */
    public static function seedTasksDomain(int $seed = 34567): array
    {
        // Set fixed seed for reproducibility
        mt_srand($seed);
        
        // Create tenant
        $tenant = self::createTenant([
            'name' => 'Tasks Test Tenant',
            'slug' => 'tasks-test-tenant-' . $seed,
            'status' => 'active',
        ]);
        
        // Create users with different roles
        $users = [];
        $users['project_manager'] = self::createUser($tenant, [
            'name' => 'Tasks PM User',
            'email' => 'pm@tasks-test.test',
            'password' => 'password',
            'role' => 'project_manager',
        ]);
        
        $users['team_member_1'] = self::createUser($tenant, [
            'name' => 'Tasks Team Member 1',
            'email' => 'member1@tasks-test.test',
            'password' => 'password',
            'role' => 'member',
        ]);
        
        $users['team_member_2'] = self::createUser($tenant, [
            'name' => 'Tasks Team Member 2',
            'email' => 'member2@tasks-test.test',
            'password' => 'password',
            'role' => 'member',
        ]);
        
        // Create a project for tasks
        $project = \App\Models\Project::create([
            'tenant_id' => $tenant->id,
            'code' => 'TASK-PROJ-' . $seed,
            'name' => 'Tasks Test Project',
            'description' => 'Project for tasks domain testing',
            'status' => 'active',
            'owner_id' => $users['project_manager']->id,
            'budget_total' => 50000.00,
            'start_date' => now(),
            'end_date' => now()->addMonths(3),
        ]);
        
        // Create a component for tasks
        $component = \App\Models\Component::create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'name' => 'Tasks Test Component',
            'type' => 'phase',
            'status' => 'active',
        ]);
        
        // Create tasks with different statuses
        $tasks = [];
        
        // Backlog task
        $tasks['backlog'] = \App\Models\Task::create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'component_id' => $component->id,
            'name' => 'Backlog Task',
            'title' => 'Backlog Task',
            'description' => 'A task in backlog',
            'status' => 'backlog',
            'priority' => 'normal',
            'estimated_hours' => 8.0,
            'actual_hours' => 0.0,
            'progress_percent' => 0.0,
        ]);
        
        // In progress task
        $tasks['in_progress'] = \App\Models\Task::create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'component_id' => $component->id,
            'name' => 'In Progress Task',
            'title' => 'In Progress Task',
            'description' => 'A task in progress',
            'status' => 'in_progress',
            'priority' => 'high',
            'estimated_hours' => 16.0,
            'actual_hours' => 4.0,
            'progress_percent' => 25.0,
            'assignee_id' => $users['team_member_1']->id,
            'created_by' => $users['project_manager']->id,
        ]);
        
        // Blocked task
        $tasks['blocked'] = \App\Models\Task::create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'component_id' => $component->id,
            'name' => 'Blocked Task',
            'title' => 'Blocked Task',
            'description' => 'A blocked task',
            'status' => 'blocked',
            'priority' => 'urgent',
            'estimated_hours' => 12.0,
            'actual_hours' => 0.0,
            'progress_percent' => 0.0,
        ]);
        
        // Done task
        $tasks['done'] = \App\Models\Task::create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'component_id' => $component->id,
            'name' => 'Done Task',
            'title' => 'Done Task',
            'description' => 'A completed task',
            'status' => 'done',
            'priority' => 'normal',
            'estimated_hours' => 10.0,
            'actual_hours' => 10.0,
            'progress_percent' => 100.0,
            'assignee_id' => $users['team_member_2']->id,
            'created_by' => $users['project_manager']->id,
        ]);
        
        // Create task assignments
        $taskAssignments = [];
        $taskAssignments[] = \App\Models\TaskAssignment::create([
            'tenant_id' => $tenant->id, // Added in migration 2025_09_17_043146
            'task_id' => $tasks['in_progress']->id,
            'user_id' => $users['team_member_1']->id,
            'role' => 'assignee',
            'assigned_at' => now(), // Required field (NOT NULL)
        ]);
        
        $taskAssignments[] = \App\Models\TaskAssignment::create([
            'tenant_id' => $tenant->id, // Added in migration 2025_09_17_043146
            'task_id' => $tasks['done']->id,
            'user_id' => $users['team_member_2']->id,
            'role' => 'assignee',
            'assigned_at' => now(), // Required field (NOT NULL)
        ]);
        
        // Create task dependencies
        $taskDependencies = [];
        // Task depends on backlog task
        $taskDependencies[] = \App\Models\TaskDependency::create([
            'tenant_id' => $tenant->id,
            'task_id' => $tasks['in_progress']->id,
            'dependency_id' => $tasks['backlog']->id, // Use dependency_id, not depends_on_task_id
        ]);
        
        // Blocked task depends on in_progress task
        $taskDependencies[] = \App\Models\TaskDependency::create([
            'tenant_id' => $tenant->id,
            'task_id' => $tasks['blocked']->id,
            'dependency_id' => $tasks['in_progress']->id, // Use dependency_id, not depends_on_task_id
        ]);
        
        return [
            'tenant' => $tenant,
            'users' => array_values($users),
            'projects' => [$project],
            'components' => [$component],
            'tasks' => array_values($tasks),
            'task_assignments' => $taskAssignments,
            'task_dependencies' => $taskDependencies,
        ];
    }

    /**
     * Seed documents domain test data with fixed seed for reproducibility
     * 
     * This method creates a complete documents domain test setup including:
     * - Tenant
     * - Users (project manager, team members)
     * - Projects (for documents to belong to)
     * - Documents with different statuses and visibility
     * - Document versions (for versioning tests)
     * 
     * TODO for Future Agent: Implement the method body following the template in
     * docs/work-packages/documents-domain-helper-guide.md (Phase 3).
     * 
     * @param int $seed Fixed seed value (default: 45678)
     * @return array{
     *     tenant: \App\Models\Tenant,
     *     users: \App\Models\User[],
     *     projects: \App\Models\Project[],
     *     documents: \App\Models\Document[],
     *     document_versions: \App\Models\DocumentVersion[]
     * }
     */
    public static function seedDocumentsDomain(int $seed = 45678): array
    {
        // Set fixed seed for reproducibility
        mt_srand($seed);
        
        // Create tenant
        $tenant = self::createTenant([
            'name' => 'Documents Test Tenant',
            'slug' => 'documents-test-tenant-' . $seed,
            'status' => 'active',
        ]);
        
        // Create users with different roles
        $users = [];
        $users['project_manager'] = self::createUser($tenant, [
            'name' => 'Documents PM User',
            'email' => 'pm@documents-test.test',
            'password' => 'password',
            'role' => 'project_manager',
        ]);
        
        $users['team_member'] = self::createUser($tenant, [
            'name' => 'Documents Team Member',
            'email' => 'member@documents-test.test',
            'password' => 'password',
            'role' => 'member',
        ]);
        
        // Create a project for documents
        $project = \App\Models\Project::create([
            'tenant_id' => $tenant->id,
            'code' => 'DOC-PROJ-' . $seed,
            'name' => 'Documents Test Project',
            'description' => 'Project for documents domain testing',
            'status' => 'active',
            'owner_id' => $users['project_manager']->id,
            'budget_total' => 30000.00,
            'start_date' => now(),
            'end_date' => now()->addMonths(2),
        ]);
        
        // Refresh project to ensure it's saved and has an ID
        $project->refresh();
        
        // Create documents with different statuses and visibility
        $documents = [];
        
        // Internal document
        $documents['internal'] = \App\Models\Document::create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'name' => 'Internal Test Document',
            'original_name' => 'internal-doc.pdf',
            'file_path' => 'documents/test/internal-doc.pdf',
            'file_type' => 'pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 102400,
            'file_hash' => md5('internal-doc-' . $seed), // Required field (NOT NULL)
            'category' => 'internal',
            'description' => 'An internal document',
            'status' => 'active',
            'uploaded_by' => $users['project_manager']->id,
            'created_by' => $users['project_manager']->id,
        ]);
        
        // Client-visible document
        $documents['client'] = \App\Models\Document::create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'name' => 'Client Test Document',
            'original_name' => 'client-doc.pdf',
            'file_path' => 'documents/test/client-doc.pdf',
            'file_type' => 'pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 204800,
            'file_hash' => md5('client-doc-' . $seed), // Required field (NOT NULL)
            'category' => 'client',
            'description' => 'A client-visible document',
            'status' => 'active',
            'uploaded_by' => $users['project_manager']->id,
            'created_by' => $users['project_manager']->id,
        ]);
        
        // Document with versions
        $documents['versioned'] = \App\Models\Document::create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'name' => 'Versioned Test Document',
            'original_name' => 'versioned-doc.pdf',
            'file_path' => 'documents/test/versioned-doc-v1.pdf',
            'file_type' => 'pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 153600,
            'file_hash' => md5('versioned-doc-' . $seed), // Required field (NOT NULL)
            'category' => 'internal',
            'description' => 'A document with multiple versions',
            'status' => 'active',
            'version' => 1,
            'is_current_version' => true,
            'uploaded_by' => $users['team_member']->id,
            'created_by' => $users['team_member']->id,
        ]);
        
        // Create document versions
        $documentVersions = [];
        
        // Version 1
        $documentVersions[] = \App\Models\DocumentVersion::create([
            'document_id' => $documents['versioned']->id,
            'version_number' => 1,
            'file_path' => 'documents/test/versioned-doc-v1.pdf',
            'storage_driver' => 'local',
            'comment' => 'Initial version',
            'created_by' => $users['team_member']->id,
        ]);
        
        // Version 2
        $documentVersions[] = \App\Models\DocumentVersion::create([
            'document_id' => $documents['versioned']->id,
            'version_number' => 2,
            'file_path' => 'documents/test/versioned-doc-v2.pdf',
            'storage_driver' => 'local',
            'comment' => 'Updated version',
            'created_by' => $users['project_manager']->id,
        ]);
        
        // Update document to point to latest version
        $documents['versioned']->update([
            'file_path' => 'documents/test/versioned-doc-v2.pdf',
            'version' => 2,
        ]);
        
        return [
            'tenant' => $tenant,
            'users' => array_values($users),
            'projects' => [$project],
            'documents' => array_values($documents),
            'document_versions' => $documentVersions,
        ];
    }

    /**
     * Seed users domain test data with fixed seed for reproducibility
     * 
     * This method creates a complete users domain test setup including:
     * - Tenant
     * - Users with different roles and statuses
     * - User profiles with different data (first_name, last_name, job_title, department)
     * - User preferences (theme, language, notifications)
     * - User roles and permissions
     * 
     * TODO for Future Agent: Implement the method body following the template in
     * docs/work-packages/users-domain-helper-guide.md (Phase 3).
     * 
     * @param int $seed Fixed seed value (default: 56789)
     * @return array{
     *     tenant: \App\Models\Tenant,
     *     users: \App\Models\User[],
     *     roles: \App\Models\Role[],
     *     permissions: \App\Models\Permission[]
     * }
     */
    public static function seedUsersDomain(int $seed = 56789): array
    {
        // Set fixed seed for reproducibility
        mt_srand($seed);
        
        // Create tenant
        $tenant = self::createTenant([
            'name' => 'Users Test Tenant',
            'slug' => 'users-test-tenant-' . $seed,
            'status' => 'active',
        ]);
        
        // Create roles
        $roles = [];
        $roleNames = ['admin', 'project_manager', 'member', 'client'];
        
        foreach ($roleNames as $roleName) {
            $roles[$roleName] = \App\Models\Role::create([
                'name' => $roleName,
                'scope' => 'system',
                // Note: allow_override column does not exist in zena_roles table
                'description' => "Users test role: {$roleName}",
                'is_active' => true,
                'tenant_id' => $tenant->id,
            ]);
        }
        
        // Create permissions (user-related)
        $permissions = [];
        $permissionData = [
            ['module' => 'users', 'action' => 'view', 'description' => 'Can view users'],
            ['module' => 'users', 'action' => 'create', 'description' => 'Can create users'],
            ['module' => 'users', 'action' => 'update', 'description' => 'Can update users'],
            ['module' => 'users', 'action' => 'delete', 'description' => 'Can delete users'],
            ['module' => 'users', 'action' => 'manage_profile', 'description' => 'Can manage profile'],
            ['module' => 'users', 'action' => 'manage_avatar', 'description' => 'Can manage avatar'],
        ];
        
        foreach ($permissionData as $permData) {
            $permissions[] = \App\Models\Permission::create([
                'code' => \App\Models\Permission::generateCode($permData['module'], $permData['action']),
                'module' => $permData['module'],
                'action' => $permData['action'],
                'description' => $permData['description'],
            ]);
        }
        
        // Attach permissions to roles
        // Admin gets all permissions
        $roles['admin']->permissions()->attach(
            collect($permissions)->pluck('id')->toArray()
        );
        
        // Project manager gets view, update, manage_profile
        $pmPerms = collect($permissions)
            ->whereIn('action', ['view', 'update', 'manage_profile'])
            ->pluck('id')
            ->toArray();
        $roles['project_manager']->permissions()->attach($pmPerms);
        
        // Member gets manage_profile, manage_avatar
        $memberPerms = collect($permissions)
            ->whereIn('action', ['manage_profile', 'manage_avatar'])
            ->pluck('id')
            ->toArray();
        $roles['member']->permissions()->attach($memberPerms);
        
        // Create users with different roles and statuses
        $users = [];
        
        // Admin user
        $users['admin'] = self::createUser($tenant, [
            'name' => 'Users Admin User',
            'email' => 'admin@users-test.test',
            'password' => 'password',
            'role' => 'admin',
            'is_active' => true,
        ]);
        $users['admin']->roles()->attach($roles['admin']->id);
        
        // Project manager user
        $users['project_manager'] = self::createUser($tenant, [
            'name' => 'Users PM User',
            'email' => 'pm@users-test.test',
            'password' => 'password',
            'role' => 'project_manager',
            'is_active' => true,
        ]);
        $users['project_manager']->roles()->attach($roles['project_manager']->id);
        
        // Member user
        $users['member'] = self::createUser($tenant, [
            'name' => 'Users Member User',
            'email' => 'member@users-test.test',
            'password' => 'password',
            'role' => 'member',
            'is_active' => true,
        ]);
        $users['member']->roles()->attach($roles['member']->id);
        
        // Inactive user
        $users['inactive'] = self::createUser($tenant, [
            'name' => 'Users Inactive User',
            'email' => 'inactive@users-test.test',
            'password' => 'password',
            'role' => 'member',
            'is_active' => false,
        ]);
        $users['inactive']->roles()->attach($roles['member']->id);
        
        // Client user
        $users['client'] = self::createUser($tenant, [
            'name' => 'Users Client User',
            'email' => 'client@users-test.test',
            'password' => 'password',
            'role' => 'client',
            'is_active' => true,
        ]);
        $users['client']->roles()->attach($roles['client']->id);
        
        return [
            'tenant' => $tenant,
            'users' => array_values($users),
            'roles' => array_values($roles),
            'permissions' => $permissions,
        ];
    }

    /**
     * Seed dashboard domain test data with fixed seed for reproducibility
     * 
     * This method creates a complete dashboard domain test setup including:
     * - Tenant
     * - Users (with different roles)
     * - Projects (for dashboard data)
     * - Dashboard widgets (available widgets in system)
     * - User dashboards (user-specific dashboard configurations)
     * - Dashboard metrics (KPI metrics)
     * - Dashboard metric values (metric data over time)
     * - Dashboard alerts
     * 
     * TODO for Future Agent: Implement the method body following the template in
     * docs/work-packages/dashboard-domain-helper-guide.md (Phase 3).
     * 
     * @param int $seed Fixed seed value (default: 67890)
     * @return array{
     *     tenant: \App\Models\Tenant,
     *     users: \App\Models\User[],
     *     projects: \App\Models\Project[],
     *     dashboard_widgets: \App\Models\DashboardWidget[],
     *     user_dashboards: \App\Models\UserDashboard[],
     *     dashboard_metrics: \App\Models\DashboardMetric[],
     *     dashboard_metric_values: \App\Models\DashboardMetricValue[],
     *     dashboard_alerts: \App\Models\DashboardAlert[]
     * }
     */
    public static function seedDashboardDomain(int $seed = 67890): array
    {
        // Set fixed seed for reproducibility
        mt_srand($seed);
        
        // Create tenant
        $tenant = self::createTenant([
            'name' => 'Dashboard Test Tenant',
            'slug' => 'dashboard-test-tenant-' . $seed,
            'status' => 'active',
        ]);
        
        // Create users with different roles
        $users = [];
        $users['admin'] = self::createUser($tenant, [
            'name' => 'Dashboard Admin User',
            'email' => 'admin@dashboard-test.test',
            'password' => 'password',
            'role' => 'admin',
        ]);
        
        $users['project_manager'] = self::createUser($tenant, [
            'name' => 'Dashboard PM User',
            'email' => 'pm@dashboard-test.test',
            'password' => 'password',
            'role' => 'project_manager',
        ]);
        
        $users['member'] = self::createUser($tenant, [
            'name' => 'Dashboard Member User',
            'email' => 'member@dashboard-test.test',
            'password' => 'password',
            'role' => 'member',
        ]);
        
        // Create a project for dashboard data
        $project = \App\Models\Project::create([
            'tenant_id' => $tenant->id,
            'code' => 'DASH-PROJ-' . $seed,
            'name' => 'Dashboard Test Project',
            'description' => 'Project for dashboard domain testing',
            'status' => 'active',
            'owner_id' => $users['project_manager']->id,
            'budget_total' => 100000.00,
            'start_date' => now(),
            'end_date' => now()->addMonths(6),
        ]);
        
        // Create dashboard widgets
        $dashboardWidgets = [];
        
        $dashboardWidgets['project_overview'] = \App\Models\DashboardWidget::create([
            'name' => 'Project Overview',
            'type' => 'card',
            'category' => 'overview',
            'config' => ['show_progress' => true, 'show_budget' => true],
            'data_source' => ['type' => 'project', 'endpoint' => '/api/projects'],
            'is_active' => true,
            'description' => 'Overview of project status',
        ]);
        
        $dashboardWidgets['budget_chart'] = \App\Models\DashboardWidget::create([
            'name' => 'Budget Chart',
            'type' => 'chart',
            'category' => 'budget',
            'config' => ['chart_type' => 'line', 'period' => 'monthly'],
            'data_source' => ['type' => 'budget', 'endpoint' => '/api/budget'],
            'is_active' => true,
            'description' => 'Budget tracking chart',
        ]);
        
        $dashboardWidgets['task_metrics'] = \App\Models\DashboardWidget::create([
            'name' => 'Task Metrics',
            'type' => 'metric',
            'category' => 'progress',
            'config' => ['show_completion' => true, 'show_overdue' => true],
            'data_source' => ['type' => 'tasks', 'endpoint' => '/api/tasks'],
            'is_active' => true,
            'description' => 'Task completion metrics',
        ]);
        
        // Create user dashboards
        $userDashboards = [];
        
        // Admin dashboard
        $userDashboards['admin'] = \App\Models\UserDashboard::create([
            'user_id' => $users['admin']->id,
            'tenant_id' => $tenant->id,
            'name' => 'Admin Dashboard',
            'layout_config' => [
                'columns' => 3,
                'rows' => 2,
            ],
            'widgets' => [
                ['widget_id' => $dashboardWidgets['project_overview']->id, 'position' => [0, 0], 'size' => [2, 1]],
                ['widget_id' => $dashboardWidgets['budget_chart']->id, 'position' => [2, 0], 'size' => [1, 2]],
                ['widget_id' => $dashboardWidgets['task_metrics']->id, 'position' => [0, 1], 'size' => [2, 1]],
            ],
            'preferences' => [
                'theme' => 'dark',
                'refresh_interval' => 60,
            ],
            'is_default' => true,
            'is_active' => true,
        ]);
        
        // Project manager dashboard
        $userDashboards['pm'] = \App\Models\UserDashboard::create([
            'user_id' => $users['project_manager']->id,
            'tenant_id' => $tenant->id,
            'name' => 'PM Dashboard',
            'layout_config' => [
                'columns' => 2,
                'rows' => 2,
            ],
            'widgets' => [
                ['widget_id' => $dashboardWidgets['project_overview']->id, 'position' => [0, 0], 'size' => [1, 1]],
                ['widget_id' => $dashboardWidgets['task_metrics']->id, 'position' => [1, 0], 'size' => [1, 1]],
            ],
            'preferences' => [
                'theme' => 'light',
                'refresh_interval' => 30,
            ],
            'is_default' => true,
            'is_active' => true,
        ]);
        
        // Create dashboard metrics
        $dashboardMetrics = [];
        
        $dashboardMetrics['project_progress'] = \App\Models\DashboardMetric::create([
            'name' => 'Project Progress',
            'category' => 'project',
            'unit' => 'percent',
            'config' => ['min' => 0, 'max' => 100, 'metric_code' => 'project.progress'],
            'is_active' => true,
            'description' => 'Overall project completion percentage',
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
        ]);
        
        $dashboardMetrics['budget_utilization'] = \App\Models\DashboardMetric::create([
            'name' => 'Budget Utilization',
            'category' => 'budget',
            'unit' => 'percent',
            'config' => ['min' => 0, 'max' => 100, 'metric_code' => 'budget.utilization'],
            'is_active' => true,
            'description' => 'Budget utilization percentage',
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
        ]);
        
        // Create dashboard metric values
        $dashboardMetricValues = [];
        
        // Metric values for project progress
        for ($i = 0; $i < 5; $i++) {
            $dashboardMetricValues[] = \App\Models\DashboardMetricValue::create([
                'metric_id' => $dashboardMetrics['project_progress']->id,
                'tenant_id' => $tenant->id,
                'project_id' => $project->id,
                'value' => 10.0 + ($i * 5.0), // 10, 15, 20, 25, 30
                'recorded_at' => now()->subDays(5 - $i),
            ]);
        }
        
        // Metric values for budget utilization
        for ($i = 0; $i < 5; $i++) {
            $dashboardMetricValues[] = \App\Models\DashboardMetricValue::create([
                'metric_id' => $dashboardMetrics['budget_utilization']->id,
                'tenant_id' => $tenant->id,
                'project_id' => $project->id,
                'value' => 20.0 + ($i * 3.0), // 20, 23, 26, 29, 32
                'recorded_at' => now()->subDays(5 - $i),
            ]);
        }
        
        // Create dashboard alerts
        $dashboardAlerts = [];
        
        // Note: dashboard_alerts table schema only has: user_id, tenant_id, message, type, is_read, metadata, read_at
        // Model has category and title in fillable, but migration doesn't create those columns
        // Using metadata to store category and title info
        $dashboardAlerts[] = \App\Models\DashboardAlert::create([
            'tenant_id' => $tenant->id,
            'user_id' => $users['project_manager']->id,
            'type' => 'warning',
            'message' => 'Budget Alert: Budget utilization is approaching limit',
            'is_read' => false,
            'metadata' => ['category' => 'budget', 'title' => 'Budget Alert'],
        ]);
        
        $dashboardAlerts[] = \App\Models\DashboardAlert::create([
            'tenant_id' => $tenant->id,
            'user_id' => $users['admin']->id,
            'type' => 'info',
            'message' => 'Project Update: Project status has been updated',
            'is_read' => false,
            'metadata' => ['category' => 'project', 'title' => 'Project Update'],
        ]);
        
        return [
            'tenant' => $tenant,
            'users' => array_values($users),
            'projects' => [$project],
            'dashboard_widgets' => array_values($dashboardWidgets),
            'user_dashboards' => array_values($userDashboards),
            'dashboard_metrics' => array_values($dashboardMetrics),
            'dashboard_metric_values' => $dashboardMetricValues,
            'dashboard_alerts' => $dashboardAlerts,
        ];
    }
}

