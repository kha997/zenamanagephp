<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Src\CoreProject\Models\Project;
use Src\CoreProject\Models\Task;
use App\Models\User;
use Carbon\Carbon;

class MockDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🌱 Starting Mock Data Seeding...');

        // Clear existing data
        $this->clearExistingData();

        // Create users
        $users = $this->createUsers();

        // Create projects
        $projects = $this->createProjects($users);

        // Create tasks
        $tasks = $this->createTasks($projects, $users);

        $this->command->info('✅ Mock data seeding completed successfully!');
        $this->command->info("📊 Created: {$users->count()} users, {$projects->count()} projects, {$tasks->count()} tasks");
    }

    /**
     * Clear existing test data
     */
    private function clearExistingData(): void
    {
        $this->command->info('🧹 Clearing existing test data...');
        
        // Delete tasks first (foreign key constraints)
        Task::where('name', 'LIKE', '%Test%')->delete();
        Task::where('name', 'LIKE', '%Mock%')->delete();
        
        // Delete projects
        Project::where('name', 'LIKE', '%Test%')->delete();
        Project::where('name', 'LIKE', '%Mock%')->delete();
        
        // Delete users (be careful with this)
        // User::where('name', 'LIKE', '%Test%')->delete();
    }

    /**
     * Create test users
     */
    private function createUsers()
    {
        $this->command->info('👥 Creating test users...');

        $users = collect([
            [
                'name' => 'John Smith',
                'email' => 'john.smith@test.com',
                'job_title' => 'Project Manager',
                'department' => 'Engineering',
                'is_active' => true
            ],
            [
                'name' => 'Sarah Wilson',
                'email' => 'sarah.wilson@test.com',
                'job_title' => 'Senior Developer',
                'department' => 'Engineering',
                'is_active' => true
            ],
            [
                'name' => 'Mike Johnson',
                'email' => 'mike.johnson@test.com',
                'job_title' => 'UI/UX Designer',
                'department' => 'Design',
                'is_active' => true
            ],
            [
                'name' => 'Alex Lee',
                'email' => 'alex.lee@test.com',
                'job_title' => 'Backend Developer',
                'department' => 'Engineering',
                'is_active' => true
            ],
            [
                'name' => 'Emma Davis',
                'email' => 'emma.davis@test.com',
                'job_title' => 'QA Tester',
                'department' => 'Quality Assurance',
                'is_active' => true
            ],
            [
                'name' => 'David Brown',
                'email' => 'david.brown@test.com',
                'job_title' => 'Business Analyst',
                'department' => 'Business',
                'is_active' => true
            ]
        ]);

        $createdUsers = collect();
        foreach ($users as $userData) {
            $user = User::create([
                'id' => \Illuminate\Support\Str::ulid(),
                'name' => $userData['name'],
                'email' => $userData['email'],
                'password' => bcrypt('password123'),
                'job_title' => $userData['job_title'],
                'department' => $userData['department'],
                'is_active' => $userData['is_active'],
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]);
            $createdUsers->push($user);
        }

        return $createdUsers;
    }

    /**
     * Create test projects
     */
    private function createProjects($users)
    {
        $this->command->info('📁 Creating test projects...');

        $projects = collect([
            [
                'name' => 'E-Commerce Platform Development',
                'description' => 'Complete e-commerce platform with modern UI/UX, payment integration, and inventory management',
                'status' => 'active',
                'priority' => 'high',
                'start_date' => Carbon::now()->subMonths(2),
                'end_date' => Carbon::now()->addMonths(4),
                'budget_planned' => 150000,
                'budget_actual' => 45000,
                'progress' => 30
            ],
            [
                'name' => 'Mobile App Development',
                'description' => 'Cross-platform mobile application for iOS and Android with real-time features',
                'status' => 'active',
                'priority' => 'medium',
                'start_date' => Carbon::now()->subMonth(),
                'end_date' => Carbon::now()->addMonths(3),
                'budget_planned' => 80000,
                'budget_actual' => 20000,
                'progress' => 25
            ],
            [
                'name' => 'Data Analytics Dashboard',
                'description' => 'Business intelligence dashboard with advanced analytics and reporting features',
                'status' => 'planning',
                'priority' => 'medium',
                'start_date' => Carbon::now()->addWeek(),
                'end_date' => Carbon::now()->addMonths(2),
                'budget_planned' => 60000,
                'budget_actual' => 0,
                'progress' => 0
            ],
            [
                'name' => 'API Integration Project',
                'description' => 'Third-party API integrations and microservices architecture implementation',
                'status' => 'active',
                'priority' => 'high',
                'start_date' => Carbon::now()->subWeeks(3),
                'end_date' => Carbon::now()->addMonths(1),
                'budget_planned' => 40000,
                'budget_actual' => 15000,
                'progress' => 40
            ],
            [
                'name' => 'Security Audit & Compliance',
                'description' => 'Comprehensive security audit and compliance implementation',
                'status' => 'completed',
                'priority' => 'high',
                'start_date' => Carbon::now()->subMonths(3),
                'end_date' => Carbon::now()->subWeek(),
                'budget_planned' => 25000,
                'budget_actual' => 25000,
                'progress' => 100
            ]
        ]);

        $createdProjects = collect();
        foreach ($projects as $index => $projectData) {
            $project = Project::create([
                'id' => \Illuminate\Support\Str::ulid(),
                'code' => 'PRJ-' . strtoupper(substr(md5($projectData['name']), 0, 8)),
                'name' => $projectData['name'],
                'description' => $projectData['description'],
                'status' => $projectData['status'],
                'priority' => $projectData['priority'],
                'start_date' => $projectData['start_date'],
                'end_date' => $projectData['end_date'],
                'budget_planned' => $projectData['budget_planned'],
                'budget_actual' => $projectData['budget_actual'],
                'progress' => $projectData['progress'],
                'pm_id' => $users->first()->id,
                'created_by' => $users->first()->id,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            $createdProjects->push($project);
        }

        return $createdProjects;
    }

    /**
     * Create test tasks
     */
    private function createTasks($projects, $users)
    {
        $this->command->info('📝 Creating test tasks...');

        $tasks = collect([
            // E-Commerce Platform Development Tasks
            [
                'name' => 'Design System Architecture',
                'description' => 'Create comprehensive design system for the e-commerce platform',
                'project_id' => 0, // Will be set to first project
                'assignee_id' => 1, // John Smith
                'status' => 'in_progress',
                'priority' => 'high',
                'start_date' => Carbon::now()->subDays(15),
                'end_date' => Carbon::now()->addDays(10),
                'estimated_hours' => 40,
                'actual_hours' => 25,
                'progress_percent' => 62,
                'tags' => 'design,architecture,system'
            ],
            [
                'name' => 'Database Schema Design',
                'description' => 'Design and implement database schema for the application',
                'project_id' => 0,
                'assignee_id' => 2, // Sarah Wilson
                'status' => 'completed',
                'priority' => 'high',
                'start_date' => Carbon::now()->subDays(20),
                'end_date' => Carbon::now()->subDays(5),
                'estimated_hours' => 32,
                'actual_hours' => 30,
                'progress_percent' => 100,
                'tags' => 'database,schema,backend'
            ],
            [
                'name' => 'Frontend Development',
                'description' => 'Develop responsive frontend components using React',
                'project_id' => 0,
                'assignee_id' => 3, // Mike Johnson
                'status' => 'pending',
                'priority' => 'medium',
                'start_date' => Carbon::now()->addDays(5),
                'end_date' => Carbon::now()->addDays(30),
                'estimated_hours' => 80,
                'actual_hours' => 0,
                'progress_percent' => 0,
                'tags' => 'frontend,react,ui'
            ],
            [
                'name' => 'Payment Integration',
                'description' => 'Integrate payment gateways and processing systems',
                'project_id' => 0,
                'assignee_id' => 4, // Alex Lee
                'status' => 'in_progress',
                'priority' => 'high',
                'start_date' => Carbon::now()->subDays(10),
                'end_date' => Carbon::now()->addDays(15),
                'estimated_hours' => 24,
                'actual_hours' => 12,
                'progress_percent' => 50,
                'tags' => 'payment,integration,backend'
            ],
            [
                'name' => 'Testing & QA',
                'description' => 'Comprehensive testing and quality assurance',
                'project_id' => 0,
                'assignee_id' => 5, // Emma Davis
                'status' => 'pending',
                'priority' => 'high',
                'start_date' => Carbon::now()->addDays(20),
                'end_date' => Carbon::now()->addDays(45),
                'estimated_hours' => 48,
                'actual_hours' => 0,
                'progress_percent' => 0,
                'tags' => 'testing,qa,quality'
            ],

            // Mobile App Development Tasks
            [
                'name' => 'Mobile App Wireframing',
                'description' => 'Create wireframes and user flow diagrams for mobile app',
                'project_id' => 1,
                'assignee_id' => 3, // Mike Johnson
                'status' => 'completed',
                'priority' => 'medium',
                'start_date' => Carbon::now()->subDays(25),
                'end_date' => Carbon::now()->subDays(10),
                'estimated_hours' => 16,
                'actual_hours' => 18,
                'progress_percent' => 100,
                'tags' => 'mobile,wireframe,design'
            ],
            [
                'name' => 'React Native Setup',
                'description' => 'Set up React Native development environment and project structure',
                'project_id' => 1,
                'assignee_id' => 2, // Sarah Wilson
                'status' => 'in_progress',
                'priority' => 'medium',
                'start_date' => Carbon::now()->subDays(15),
                'end_date' => Carbon::now()->addDays(5),
                'estimated_hours' => 20,
                'actual_hours' => 15,
                'progress_percent' => 75,
                'tags' => 'react-native,setup,mobile'
            ],
            [
                'name' => 'API Integration',
                'description' => 'Integrate mobile app with backend APIs',
                'project_id' => 1,
                'assignee_id' => 4, // Alex Lee
                'status' => 'pending',
                'priority' => 'high',
                'start_date' => Carbon::now()->addDays(3),
                'end_date' => Carbon::now()->addDays(20),
                'estimated_hours' => 30,
                'actual_hours' => 0,
                'progress_percent' => 0,
                'tags' => 'api,integration,mobile'
            ],

            // Data Analytics Dashboard Tasks
            [
                'name' => 'Requirements Analysis',
                'description' => 'Analyze business requirements for analytics dashboard',
                'project_id' => 2,
                'assignee_id' => 6, // David Brown
                'status' => 'in_progress',
                'priority' => 'medium',
                'start_date' => Carbon::now()->subDays(5),
                'end_date' => Carbon::now()->addDays(10),
                'estimated_hours' => 24,
                'actual_hours' => 8,
                'progress_percent' => 33,
                'tags' => 'analysis,requirements,business'
            ],
            [
                'name' => 'Data Pipeline Design',
                'description' => 'Design data pipeline and ETL processes',
                'project_id' => 2,
                'assignee_id' => 2, // Sarah Wilson
                'status' => 'pending',
                'priority' => 'high',
                'start_date' => Carbon::now()->addDays(8),
                'end_date' => Carbon::now()->addDays(25),
                'estimated_hours' => 40,
                'actual_hours' => 0,
                'progress_percent' => 0,
                'tags' => 'data,pipeline,etl'
            ],

            // API Integration Project Tasks
            [
                'name' => 'Microservices Architecture',
                'description' => 'Design and implement microservices architecture',
                'project_id' => 3,
                'assignee_id' => 1, // John Smith
                'status' => 'completed',
                'priority' => 'high',
                'start_date' => Carbon::now()->subDays(20),
                'end_date' => Carbon::now()->subDays(5),
                'estimated_hours' => 35,
                'actual_hours' => 38,
                'progress_percent' => 100,
                'tags' => 'microservices,architecture,backend'
            ],
            [
                'name' => 'Third-party API Integration',
                'description' => 'Integrate with external APIs and services',
                'project_id' => 3,
                'assignee_id' => 4, // Alex Lee
                'status' => 'in_progress',
                'priority' => 'medium',
                'start_date' => Carbon::now()->subDays(10),
                'end_date' => Carbon::now()->addDays(15),
                'estimated_hours' => 25,
                'actual_hours' => 12,
                'progress_percent' => 48,
                'tags' => 'api,integration,external'
            ],

            // Security Audit Tasks
            [
                'name' => 'Security Vulnerability Assessment',
                'description' => 'Comprehensive security vulnerability assessment',
                'project_id' => 4,
                'assignee_id' => 5, // Emma Davis
                'status' => 'completed',
                'priority' => 'high',
                'start_date' => Carbon::now()->subDays(60),
                'end_date' => Carbon::now()->subDays(30),
                'estimated_hours' => 40,
                'actual_hours' => 42,
                'progress_percent' => 100,
                'tags' => 'security,vulnerability,assessment'
            ],
            [
                'name' => 'Compliance Implementation',
                'description' => 'Implement security compliance measures',
                'project_id' => 4,
                'assignee_id' => 1, // John Smith
                'status' => 'completed',
                'priority' => 'high',
                'start_date' => Carbon::now()->subDays(35),
                'end_date' => Carbon::now()->subDays(10),
                'estimated_hours' => 30,
                'actual_hours' => 32,
                'progress_percent' => 100,
                'tags' => 'compliance,security,implementation'
            ]
        ]);

        $createdTasks = collect();
        foreach ($tasks as $taskData) {
            $task = Task::create([
                'id' => \Illuminate\Support\Str::ulid(),
                'project_id' => $projects[$taskData['project_id']]->id,
                'name' => $taskData['name'],
                'description' => $taskData['description'],
                'assignee_id' => $users[$taskData['assignee_id'] - 1]->id,
                'status' => $taskData['status'],
                'priority' => $taskData['priority'],
                'start_date' => $taskData['start_date'],
                'end_date' => $taskData['end_date'],
                'estimated_hours' => $taskData['estimated_hours'],
                'actual_hours' => $taskData['actual_hours'],
                'progress_percent' => $taskData['progress_percent'],
                'tags' => $taskData['tags'],
                'created_at' => now(),
                'updated_at' => now()
            ]);
            $createdTasks->push($task);
        }

        return $createdTasks;
    }
}
