<?php declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use App\Models\Client;
use App\Models\Quote;
use Illuminate\Support\Facades\Hash;

/**
 * Test Data Factory
 * 
 * Creates consistent test data for all test cases
 */
class TestDataFactory
{
    private static $tenants = [];
    private static $users = [];
    private static $projects = [];
    private static $tasks = [];
    private static $clients = [];
    private static $quotes = [];

    /**
     * Create test tenant
     */
    public static function createTenant(array $attributes = []): Tenant
    {
        $defaultAttributes = [
            'name' => 'Test Company',
            'domain' => 'test-company.com',
            'is_active' => true,
            'settings' => [
                'timezone' => 'UTC',
                'date_format' => 'Y-m-d',
                'currency' => 'USD',
            ],
        ];

        $tenant = Tenant::factory()->create(array_merge($defaultAttributes, $attributes));
        self::$tenants[] = $tenant;
        
        return $tenant;
    }

    /**
     * Create test user
     */
    public static function createUser(Tenant $tenant, array $attributes = []): User
    {
        $defaultAttributes = [
            'tenant_id' => $tenant->id,
            'name' => 'Test User',
            'email' => 'test@test-company.com',
            'password' => Hash::make('password'),
            'is_active' => true,
            'role' => 'member',
            'profile_data' => json_encode([
                'phone' => '+1234567890',
                'department' => 'Engineering',
                'position' => 'Developer',
            ]),
        ];

        $user = User::factory()->create(array_merge($defaultAttributes, $attributes));
        self::$users[] = $user;
        
        return $user;
    }

    /**
     * Create test project
     */
    public static function createProject(Tenant $tenant, User $user, array $attributes = []): Project
    {
        $defaultAttributes = [
            'tenant_id' => $tenant->id,
            'name' => 'Test Project',
            'description' => 'A test project for testing purposes',
            'status' => 'active',
            'priority' => 'medium',
            'start_date' => now()->subDays(30),
            'end_date' => now()->addDays(30),
            'budget_total' => 10000.00,
            'created_by' => $user->id,
        ];

        $project = Project::factory()->create(array_merge($defaultAttributes, $attributes));
        self::$projects[] = $project;
        
        return $project;
    }

    /**
     * Create test task
     */
    public static function createTask(Project $project, User $assignee, array $attributes = []): Task
    {
        $defaultAttributes = [
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'title' => 'Test Task',
            'description' => 'A test task for testing purposes',
            'status' => 'pending',
            'priority' => 'medium',
            'assignee_id' => $assignee->id,
            'end_date' => now()->addDays(7),
            'estimated_hours' => 8,
        ];

        $task = Task::factory()->create(array_merge($defaultAttributes, $attributes));
        self::$tasks[] = $task;
        
        return $task;
    }

    /**
     * Create test client
     */
    public static function createClient(Tenant $tenant, array $attributes = []): Client
    {
        $defaultAttributes = [
            'tenant_id' => $tenant->id,
            'name' => 'Test Client',
            'email' => 'client@test-company.com',
            'phone' => '+1234567890',
            'company' => 'Test Client Company',
            'address' => '123 Test Street, Test City, TC 12345',
        ];

        $client = Client::factory()->create(array_merge($defaultAttributes, $attributes));
        self::$clients[] = $client;
        
        return $client;
    }

    /**
     * Create test quote
     */
    public static function createQuote(Tenant $tenant, Client $client, array $attributes = []): Quote
    {
        $defaultAttributes = [
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'title' => 'Test Quote',
            'description' => 'A test quote for testing purposes',
            'total_amount' => 5000.00,
            'status' => 'draft',
            'valid_until' => now()->addDays(30),
        ];

        $quote = Quote::factory()->create(array_merge($defaultAttributes, $attributes));
        self::$quotes[] = $quote;
        
        return $quote;
    }

    /**
     * Create complete test scenario
     */
    public static function createTestScenario(): array
    {
        // Create tenant
        $tenant = self::createTenant([
            'name' => 'Test Scenario Company',
            'domain' => 'test-scenario.com',
        ]);

        // Create users
        $admin = self::createUser($tenant, [
            'name' => 'Admin User',
            'email' => 'admin-' . uniqid() . '@test-scenario.com',
            'role' => 'pm',
        ]);

        $member = self::createUser($tenant, [
            'name' => 'Member User',
            'email' => 'member-' . uniqid() . '@test-scenario.com',
            'role' => 'member',
        ]);

        // Create projects
        $project1 = self::createProject($tenant, $admin, [
            'name' => 'Active Project',
            'status' => 'active',
            'priority' => 'high',
        ]);

        $project2 = self::createProject($tenant, $admin, [
            'name' => 'Completed Project',
            'status' => 'completed',
            'priority' => 'medium',
        ]);

        // Create tasks
        $task1 = self::createTask($project1, $member, [
            'title' => 'Active Task',
            'status' => 'pending',
            'priority' => 'high',
        ]);

        $task2 = self::createTask($project1, $member, [
            'title' => 'Completed Task',
            'status' => 'completed',
            'priority' => 'medium',
        ]);

        $task3 = self::createTask($project2, $admin, [
            'title' => 'Project 2 Task',
            'status' => 'completed',
            'priority' => 'low',
        ]);

        // Create client
        $client = self::createClient($tenant, [
            'name' => 'Test Client',
            'company' => 'Test Client Company',
        ]);

        // Create quotes
        $quote1 = self::createQuote($tenant, $client, [
            'title' => 'Active Quote',
            'status' => 'draft',
            'total_amount' => 10000.00,
        ]);

        $quote2 = self::createQuote($tenant, $client, [
            'title' => 'Approved Quote',
            'status' => 'accepted',
            'total_amount' => 15000.00,
        ]);

        return [
            'tenant' => $tenant,
            'users' => [
                'admin' => $admin,
                'member' => $member,
            ],
            'projects' => [
                'active' => $project1,
                'completed' => $project2,
            ],
            'tasks' => [
                'active' => $task1,
                'completed' => $task2,
                'project2' => $task3,
            ],
            'clients' => [
                'main' => $client,
            ],
            'quotes' => [
                'draft' => $quote1,
                'approved' => $quote2,
            ],
        ];
    }

    /**
     * Create multiple tenants scenario
     */
    public static function createMultiTenantScenario(): array
    {
        $scenarios = [];

        for ($i = 1; $i <= 3; $i++) {
            $scenarios["tenant_{$i}"] = self::createTestScenario();
        }

        return $scenarios;
    }

    /**
     * Clean up test data
     */
    public static function cleanup(): void
    {
        // Delete in reverse order to respect foreign key constraints
        foreach (array_reverse(self::$quotes) as $quote) {
            $quote->delete();
        }
        foreach (array_reverse(self::$clients) as $client) {
            $client->delete();
        }
        foreach (array_reverse(self::$tasks) as $task) {
            $task->delete();
        }
        foreach (array_reverse(self::$projects) as $project) {
            $project->delete();
        }
        foreach (array_reverse(self::$users) as $user) {
            $user->delete();
        }
        foreach (array_reverse(self::$tenants) as $tenant) {
            $tenant->delete();
        }

        // Clear arrays
        self::$tenants = [];
        self::$users = [];
        self::$projects = [];
        self::$tasks = [];
        self::$clients = [];
        self::$quotes = [];
    }

    /**
     * Get test data statistics
     */
    public static function getStats(): array
    {
        return [
            'tenants' => count(self::$tenants),
            'users' => count(self::$users),
            'projects' => count(self::$projects),
            'tasks' => count(self::$tasks),
            'clients' => count(self::$clients),
            'quotes' => count(self::$quotes),
        ];
    }

    /**
     * Create realistic test data
     */
    public static function createRealisticData(): array
    {
        $tenant = self::createTenant([
            'name' => 'Acme Construction',
            'domain' => 'acme-construction.com',
        ]);

        $pm = self::createUser($tenant, [
            'name' => 'John Smith',
            'email' => 'john.smith@acme-construction.com',
            'role' => 'pm',
            'profile_data' => json_encode([
                'phone' => '+1-555-0123',
                'department' => 'Project Management',
                'position' => 'Senior Project Manager',
            ]),
        ]);

        $engineer = self::createUser($tenant, [
            'name' => 'Sarah Johnson',
            'email' => 'sarah.johnson@acme-construction.com',
            'role' => 'member',
            'profile_data' => json_encode([
                'phone' => '+1-555-0124',
                'department' => 'Engineering',
                'position' => 'Civil Engineer',
            ]),
        ]);

        $architect = self::createUser($tenant, [
            'name' => 'Mike Davis',
            'email' => 'mike.davis@acme-construction.com',
            'role' => 'member',
            'profile_data' => json_encode([
                'phone' => '+1-555-0125',
                'department' => 'Architecture',
                'position' => 'Senior Architect',
            ]),
        ]);

        $project = self::createProject($tenant, $pm, [
            'name' => 'Downtown Office Complex',
            'description' => 'Construction of a 20-story office building in downtown area',
            'status' => 'active',
            'priority' => 'high',
            'budget_total' => 5000000.00,
            'start_date' => now()->subMonths(6),
            'end_date' => now()->addMonths(18),
        ]);

        $tasks = [
            self::createTask($project, $engineer, [
                'title' => 'Foundation Design Review',
                'description' => 'Review and approve foundation design specifications',
                'status' => 'completed',
                'priority' => 'high',
                'end_date' => now()->subDays(30),
            ]),
            self::createTask($project, $architect, [
                'title' => 'Floor Plan Finalization',
                'description' => 'Finalize floor plans for all 20 stories',
                'status' => 'in_progress',
                'priority' => 'high',
                'end_date' => now()->addDays(14),
            ]),
            self::createTask($project, $engineer, [
                'title' => 'Structural Analysis',
                'description' => 'Perform structural analysis for earthquake resistance',
                'status' => 'pending',
                'priority' => 'medium',
                'end_date' => now()->addDays(30),
            ]),
        ];

        $client = self::createClient($tenant, [
            'name' => 'Metro Development Corp',
            'email' => 'contact@metrodev.com',
            'company' => 'Metro Development Corporation',
            'phone' => '+1-555-0200',
            'address' => '456 Business Ave, Metro City, MC 54321',
        ]);

        $quote = self::createQuote($tenant, $client, [
            'title' => 'Downtown Office Complex - Phase 1',
            'description' => 'Construction services for foundation and first 10 stories',
            'total_amount' => 2500000.00,
            'status' => 'accepted',
            'valid_until' => now()->addDays(60),
        ]);

        return [
            'tenant' => $tenant,
            'users' => [
                'pm' => $pm,
                'engineer' => $engineer,
                'architect' => $architect,
            ],
            'project' => $project,
            'tasks' => $tasks,
            'client' => $client,
            'quote' => $quote,
        ];
    }
}
