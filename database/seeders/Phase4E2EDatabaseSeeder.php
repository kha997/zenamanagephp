<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use App\Models\Task;
use App\Models\Document;
use Carbon\Carbon;

class Phase4E2EDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds for Phase 4 Regression Testing.
     * This seeder creates extended test data for comprehensive regression testing.
     */
    public function run()
    {
        $this->command->info('🌱 Starting Phase 4 E2E Database Seeding...');
        
        // Ensure we have tenants first
        $this->ensureTenantsExist();
        
        // Create extended users with all role combinations
        $this->createExtendedUsers();
        
        // Create large datasets for performance testing
        $this->createLargeDatasets();
        
        // Create multi-language content
        $this->createMultiLanguageContent();
        
        // Create timezone-aware data
        $this->createTimezoneData();
        
        // Create performance test data
        $this->createPerformanceTestData();
        
        // Create security test data
        $this->createSecurityTestData();
        
        $this->command->info('✅ Phase 4 E2E Database Seeding completed!');
    }
    
    /**
     * Ensure tenants exist for Phase 4 testing
     */
    private function ensureTenantsExist()
    {
        $this->command->info('🏢 Ensuring tenants exist...');
        
        // Create ZENA tenant if not exists
        $zenaTenant = Tenant::firstOrCreate(
            ['name' => 'ZENA Construction'],
            [
                'domain' => 'zena.local',
                'is_active' => true,
                'plan' => 'enterprise',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        
        // Create TTF tenant if not exists
        $ttfTenant = Tenant::firstOrCreate(
            ['name' => 'TTF Engineering'],
            [
                'domain' => 'ttf.local',
                'is_active' => true,
                'plan' => 'professional',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        
        $this->command->info("✅ Tenants ready: {$zenaTenant->name}, {$ttfTenant->name}");
    }
    
    /**
     * Create extended users with all role combinations for RBAC testing
     */
    private function createExtendedUsers()
    {
        $this->command->info('👥 Creating extended users with all role combinations...');
        
        $tenants = Tenant::all();
        
        foreach ($tenants as $tenant) {
            $this->createUsersForTenant($tenant);
        }
    }
    
    /**
     * Create users for a specific tenant with all role combinations
     */
    private function createUsersForTenant($tenant)
    {
        $roles = [
            'super_admin' => 2,
            'admin' => 5,
            'project_manager' => 10,
            'developer' => 15,
            'client' => 20,
            'guest' => 5
        ];
        
        foreach ($roles as $role => $count) {
            for ($i = 1; $i <= $count; $i++) {
                $this->createUserWithRole($tenant, $role, $i);
            }
        }
    }
    
    /**
     * Create a user with specific role and attributes
     */
    private function createUserWithRole($tenant, $role, $index)
    {
        $userData = [
            'email' => "{$role}_{$index}@{$tenant->slug}.local",
            'name' => "{$tenant->name} {$role} {$index}",
            'role' => $role,
            'password' => Hash::make('password'),
            'tenant_id' => $tenant->id,
            'is_active' => true,
            'email_verified_at' => now(),
            'timezone' => $this->getRandomTimezone(),
            'language' => $this->getRandomLanguage(),
            'last_login_at' => $this->getRandomLastLogin(),
            'created_at' => $this->getRandomCreatedAt(),
            'preferences' => $this->getUserPreferences($role),
        ];
        
        $user = User::firstOrCreate(
            ['email' => $userData['email']],
            $userData
        );
        
        // Update preferences if user already exists
        if (!$user->wasRecentlyCreated) {
            $user->update([
                'role' => $userData['role'],
                'timezone' => $userData['timezone'],
                'language' => $userData['language'],
                'preferences' => $userData['preferences'],
            ]);
        }
        
        return $user;
    }
    
    /**
     * Create large datasets for performance testing
     */
    private function createLargeDatasets()
    {
        $this->command->info('📊 Creating large datasets for performance testing...');
        
        $tenants = Tenant::all();
        
        foreach ($tenants as $tenant) {
            // Create 100+ projects per tenant
            $this->createProjectsForTenant($tenant, 100);
            
            // Create 500+ tasks per tenant
            $this->createTasksForTenant($tenant, 500);
            
            // Create 200+ documents per tenant
            $this->createDocumentsForTenant($tenant, 200);
        }
    }
    
    /**
     * Create projects for a tenant
     */
    private function createProjectsForTenant($tenant, $count)
    {
        $statuses = ['planning', 'active', 'on_hold', 'completed', 'cancelled'];
        $types = ['web_development', 'mobile_app', 'consulting', 'maintenance'];
        
        for ($i = 1; $i <= $count; $i++) {
            $projectData = [
                'code' => "PRJ-{$tenant->id}-{$i}",
                'name' => "{$tenant->name} Project {$i}",
                'description' => "Description for {$tenant->name} Project {$i}",
                'status' => $statuses[array_rand($statuses)],
                'tenant_id' => $tenant->id,
                'owner_id' => $this->getRandomUserFromTenant($tenant)->id,
                'budget_total' => rand(1000, 100000),
                'progress' => rand(0, 100),
                'start_date' => $this->getRandomDate(),
                'end_date' => $this->getRandomDate(),
                'created_at' => $this->getRandomCreatedAt(),
            ];
            
            Project::firstOrCreate(
                ['name' => $projectData['name'], 'tenant_id' => $tenant->id],
                $projectData
            );
        }
    }
    
    /**
     * Create tasks for a tenant
     */
    private function createTasksForTenant($tenant, $count)
    {
        $priorities = ['low', 'medium', 'high', 'critical'];
        $statuses = ['todo', 'in_progress', 'review', 'done', 'cancelled'];
        $types = ['development', 'testing', 'documentation', 'review'];
        
        $projects = Project::where('tenant_id', $tenant->id)->get();
        
        for ($i = 1; $i <= $count; $i++) {
            $taskData = [
                'name' => "Task {$i} for {$tenant->name}",
                'title' => "Task {$i} for {$tenant->name}",
                'description' => "Description for Task {$i}",
                'priority' => $priorities[array_rand($priorities)],
                'status' => $statuses[array_rand($statuses)],
                'tenant_id' => $tenant->id,
                'project_id' => $projects->random()->id,
                'assigned_to' => $this->getRandomUserFromTenant($tenant)->id,
                'created_by' => $this->getRandomUserFromTenant($tenant)->id,
                'start_date' => $this->getRandomDate(),
                'end_date' => $this->getRandomDate(),
                'created_at' => $this->getRandomCreatedAt(),
            ];
            
            Task::create($taskData);
        }
    }
    
    /**
     * Create documents for a tenant
     */
    private function createDocumentsForTenant($tenant, $count)
    {
        $types = ['pdf', 'docx', 'xlsx', 'jpg', 'png', 'txt'];
        $categories = ['requirements', 'design', 'code', 'testing', 'documentation'];
        
        $projects = Project::where('tenant_id', $tenant->id)->get();
        
        for ($i = 1; $i <= $count; $i++) {
            $documentData = [
                'name' => "Document {$i} for {$tenant->name}",
                'description' => "Description for Document {$i}",
                'type' => $types[array_rand($types)],
                'category' => $categories[array_rand($categories)],
                'tenant_id' => $tenant->id,
                'project_id' => $projects->random()->id,
                'uploaded_by' => $this->getRandomUserFromTenant($tenant)->id,
                'file_size' => rand(1024, 10485760), // 1KB to 10MB
                'file_path' => "/documents/{$tenant->slug}/document_{$i}.{$types[array_rand($types)]}",
                'version' => rand(1, 5),
                'created_at' => $this->getRandomCreatedAt(),
            ];
            
            Document::create($documentData);
        }
    }
    
    /**
     * Create multi-language content
     */
    private function createMultiLanguageContent()
    {
        $this->command->info('🌐 Creating multi-language content...');
        
        // Create Vietnamese content for existing projects
        $projects = Project::all();
        
        foreach ($projects as $project) {
            if (rand(1, 3) === 1) { // 1/3 chance for Vietnamese content
                $project->update([
                    'name' => "Dự án {$project->id}",
                    'description' => "Mô tả cho dự án {$project->id}",
                ]);
            }
        }
        
        // Create Vietnamese content for tasks
        $tasks = Task::all();
        
        foreach ($tasks as $task) {
            if (rand(1, 4) === 1) { // 1/4 chance for Vietnamese content
                $task->update([
                    'title' => "Nhiệm vụ {$task->id}",
                    'description' => "Mô tả cho nhiệm vụ {$task->id}",
                ]);
            }
        }
    }
    
    /**
     * Create timezone-aware data
     */
    private function createTimezoneData()
    {
        $this->command->info('🕐 Creating timezone-aware data...');
        
        // Update users with various timezones
        $timezones = [
            'Asia/Ho_Chi_Minh',
            'UTC',
            'America/New_York',
            'Europe/London',
            'Asia/Tokyo'
        ];
        
        $users = User::all();
        
        foreach ($users as $user) {
            $user->update([
                'timezone' => $timezones[array_rand($timezones)],
                'last_login_at' => $this->getRandomLastLogin(),
            ]);
        }
        
        // Update projects with timezone-aware dates
        $projects = Project::all();
        
        foreach ($projects as $project) {
            $project->update([
                'start_date' => $this->getRandomDate(),
                'end_date' => $this->getRandomDate(),
            ]);
        }
    }
    
    /**
     * Create performance test data
     */
    private function createPerformanceTestData()
    {
        $this->command->info('⚡ Creating performance test data...');
        
        // Create large files for upload testing
        $this->createLargeFiles();
        
        // Create complex data relationships
        $this->createComplexRelationships();
        
        // Create data for load testing
        $this->createLoadTestData();
    }
    
    /**
     * Create large files for performance testing
     */
    private function createLargeFiles()
    {
        $documents = Document::all();
        
        foreach ($documents as $document) {
            if (rand(1, 10) === 1) { // 1/10 chance for large file
                $document->update([
                    'file_size' => rand(10485760, 104857600), // 10MB to 100MB
                ]);
            }
        }
    }
    
    /**
     * Create complex data relationships
     */
    private function createComplexRelationships()
    {
        // Create task dependencies
        $tasks = Task::all();
        
        foreach ($tasks as $task) {
            if (rand(1, 5) === 1) { // 1/5 chance for dependency
                $dependentTask = $tasks->where('id', '!=', $task->id)->random();
                $task->update([
                    'depends_on' => $dependentTask->id,
                ]);
            }
        }
    }
    
    /**
     * Create data for load testing
     */
    private function createLoadTestData()
    {
        // Create additional users for concurrent testing
        $tenants = Tenant::all();
        
        foreach ($tenants as $tenant) {
            for ($i = 1; $i <= 20; $i++) {
                $this->createUserWithRole($tenant, 'member', "load_{$i}");
            }
        }
    }
    
    /**
     * Create security test data
     */
    private function createSecurityTestData()
    {
        $this->command->info('🔒 Creating security test data...');
        
        // Create users with malicious content for testing
        $this->createMaliciousContent();
        
        // Create invalid data for validation testing
        $this->createInvalidData();
        
        // Create edge cases for boundary testing
        $this->createEdgeCases();
    }
    
    /**
     * Create malicious content for security testing
     */
    private function createMaliciousContent()
    {
        $maliciousNames = [
            "'; DROP TABLE users; --",
            "<script>alert('XSS')</script>",
            "admin' OR '1'='1",
            "../../etc/passwd",
            "javascript:alert('XSS')"
        ];
        
        foreach ($maliciousNames as $index => $name) {
            User::create([
                'email' => "malicious_{$index}@test.local",
                'name' => $name,
                'role' => 'member',
                'password' => Hash::make('password'),
                'tenant_id' => Tenant::first()->id,
                'is_active' => false, // Mark as inactive for safety
            ]);
        }
    }
    
    /**
     * Create invalid data for validation testing
     */
    private function createInvalidData()
    {
        $invalidEmails = [
            'invalid-email',
            '@invalid.com',
            'invalid@',
            'invalid@.com',
            'invalid@com.',
        ];
        
        foreach ($invalidEmails as $index => $email) {
            User::create([
                'email' => $email,
                'name' => "Invalid User {$index}",
                'role' => 'member',
                'password' => Hash::make('password'),
                'tenant_id' => Tenant::first()->id,
                'is_active' => false, // Mark as inactive for safety
            ]);
        }
    }
    
    /**
     * Create edge cases for boundary testing
     */
    private function createEdgeCases()
    {
        // Create user with maximum length name
        User::create([
            'email' => 'maxlength@test.local',
            'name' => str_repeat('A', 255), // Maximum length
            'role' => 'member',
            'password' => Hash::make('password'),
            'tenant_id' => Tenant::first()->id,
            'is_active' => true,
        ]);
        
        // Create project with maximum length name
        Project::create([
            'name' => str_repeat('B', 255), // Maximum length
            'description' => str_repeat('C', 1000), // Large description
            'status' => 'planning',
            'tenant_id' => Tenant::first()->id,
            'owner_id' => User::first()->id,
            'budget' => 999999999, // Large budget
            'progress' => 100, // Maximum progress
        ]);
    }
    
    /**
     * Helper methods
     */
    private function getRandomTimezone()
    {
        $timezones = [
            'Asia/Ho_Chi_Minh',
            'UTC',
            'America/New_York',
            'Europe/London',
            'Asia/Tokyo'
        ];
        
        return $timezones[array_rand($timezones)];
    }
    
    private function getRandomLanguage()
    {
        $languages = ['en', 'vi'];
        return $languages[array_rand($languages)];
    }
    
    private function getRandomLastLogin()
    {
        $days = rand(0, 30);
        return now()->subDays($days);
    }
    
    private function getRandomCreatedAt()
    {
        $days = rand(0, 365);
        return now()->subDays($days);
    }
    
    private function getRandomDate()
    {
        $days = rand(-30, 365);
        return now()->addDays($days);
    }
    
    private function getUserPreferences($role)
    {
        $preferences = [
            'theme' => ['light', 'dark', 'auto'][array_rand(['light', 'dark', 'auto'])],
            'language' => $this->getRandomLanguage(),
            'timezone' => $this->getRandomTimezone(),
            'notifications' => [
                'email' => rand(0, 1) === 1,
                'in_app' => rand(0, 1) === 1,
                'sms' => rand(0, 1) === 1,
            ],
            'dashboard' => [
                'widgets' => $this->getDefaultWidgets($role),
                'layout' => 'grid',
            ]
        ];
        
        return $preferences;
    }
    
    private function getDefaultWidgets($role)
    {
        $widgets = [
            'super_admin' => ['kpi', 'projects', 'users', 'alerts'],
            'admin' => ['kpi', 'projects', 'users', 'alerts'],
            'project_manager' => ['kpi', 'projects', 'tasks', 'alerts'],
            'developer' => ['tasks', 'alerts', 'activity'],
            'client' => ['projects', 'alerts'],
            'guest' => ['alerts']
        ];
        
        return $widgets[$role] ?? ['alerts'];
    }
    
    private function getRandomUserFromTenant($tenant)
    {
        return User::where('tenant_id', $tenant->id)->inRandomOrder()->first();
    }
}
