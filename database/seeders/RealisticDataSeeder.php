<?php declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\Notification;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * Realistic Data Seeder
 * 
 * Tạo dữ liệu mẫu thực tế với:
 * - Users với các roles khác nhau (super_admin, PM, Member, Client)
 * - Projects với các status khác nhau
 * - Tasks với assignments
 * - Task Comments
 * - Notifications
 */
class RealisticDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🌱 Seeding realistic test data...');

        // Lấy hoặc tạo tenant đầu tiên
        $tenant = Tenant::first();
        if (!$tenant) {
            $this->command->warn('No tenant found. Please run TenantSeeder first.');
            return;
        }

        // Tạo users với các roles
        $users = $this->createUsersWithRoles($tenant);

        // Tạo projects với các status
        $projects = $this->createProjects($tenant, $users);

        // Tạo tasks với assignments
        $this->createTasksWithAssignments($tenant, $projects, $users);

        // Tạo task comments
        $this->createTaskComments($tenant, $users);

        // Tạo notifications
        $this->createNotifications($tenant, $users, $projects);

        $this->command->info('✅ Realistic test data seeded successfully!');
        $this->command->info("   - Users: " . $users->count());
        $this->command->info("   - Projects: " . $projects->count());
        $this->command->info("   - Tasks: " . Task::where('tenant_id', $tenant->id)->count());
        $this->command->info("   - Comments: " . TaskComment::where('tenant_id', $tenant->id)->count());
        $this->command->info("   - Notifications: " . Notification::where('tenant_id', $tenant->id)->count());
    }

    /**
     * Tạo users với các roles khác nhau
     */
    private function createUsersWithRoles(Tenant $tenant): \Illuminate\Support\Collection
    {
        $this->command->info('   Creating users with roles...');

        // Lấy các roles
        $superAdminRole = Role::where('name', 'super_admin')->first();
        $pmRole = Role::where('name', 'project_manager')->orWhere('name', 'PM')->first();
        $memberRole = Role::where('name', 'member')->orWhere('name', 'Member')->first();
        $clientRole = Role::where('name', 'client')->orWhere('name', 'Client')->first();

        $users = collect();

        // Super Admin
        $superAdmin = User::updateOrCreate(
            ['email' => 'admin@zenamanage.test'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Super Admin',
                'email' => 'admin@zenamanage.test',
                'password' => Hash::make('password'),
                'is_active' => true,
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'department' => 'IT',
                'job_title' => 'System Administrator',
            ]
        );
        if ($superAdminRole) {
            $superAdmin->roles()->syncWithoutDetaching([$superAdminRole->id]);
        }
        $users->push($superAdmin);

        // Project Managers
        $pmUsers = [
            [
                'name' => 'John Project Manager',
                'email' => 'pm1@zenamanage.test',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'department' => 'Projects',
                'job_title' => 'Project Manager',
            ],
            [
                'name' => 'Jane Project Manager',
                'email' => 'pm2@zenamanage.test',
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'department' => 'Projects',
                'job_title' => 'Senior Project Manager',
            ],
        ];

        foreach ($pmUsers as $pmData) {
            $pm = User::updateOrCreate(
                ['email' => $pmData['email']],
                array_merge($pmData, [
                    'tenant_id' => $tenant->id,
                    'password' => Hash::make('password'),
                    'is_active' => true,
                ])
            );
            if ($pmRole) {
                $pm->roles()->syncWithoutDetaching([$pmRole->id]);
            }
            $users->push($pm);
        }

        // Team Members
        $memberUsers = [
            [
                'name' => 'Alice Developer',
                'email' => 'member1@zenamanage.test',
                'first_name' => 'Alice',
                'last_name' => 'Johnson',
                'department' => 'Engineering',
                'job_title' => 'Senior Developer',
            ],
            [
                'name' => 'Bob Designer',
                'email' => 'member2@zenamanage.test',
                'first_name' => 'Bob',
                'last_name' => 'Williams',
                'department' => 'Design',
                'job_title' => 'UI/UX Designer',
            ],
            [
                'name' => 'Charlie Tester',
                'email' => 'member3@zenamanage.test',
                'first_name' => 'Charlie',
                'last_name' => 'Brown',
                'department' => 'QA',
                'job_title' => 'QA Engineer',
            ],
            [
                'name' => 'Diana Analyst',
                'email' => 'member4@zenamanage.test',
                'first_name' => 'Diana',
                'last_name' => 'Miller',
                'department' => 'Business',
                'job_title' => 'Business Analyst',
            ],
        ];

        foreach ($memberUsers as $memberData) {
            $member = User::updateOrCreate(
                ['email' => $memberData['email']],
                array_merge($memberData, [
                    'tenant_id' => $tenant->id,
                    'password' => Hash::make('password'),
                    'is_active' => true,
                ])
            );
            if ($memberRole) {
                $member->roles()->syncWithoutDetaching([$memberRole->id]);
            }
            $users->push($member);
        }

        // Clients
        $clientUsers = [
            [
                'name' => 'Mr. Client',
                'email' => 'client1@zenamanage.test',
                'first_name' => 'Client',
                'last_name' => 'User',
                'department' => 'Client',
                'job_title' => 'CEO',
            ],
            [
                'name' => 'Ms. Stakeholder',
                'email' => 'client2@zenamanage.test',
                'first_name' => 'Stakeholder',
                'last_name' => 'User',
                'department' => 'Client',
                'job_title' => 'Product Owner',
            ],
        ];

        foreach ($clientUsers as $clientData) {
            $client = User::updateOrCreate(
                ['email' => $clientData['email']],
                array_merge($clientData, [
                    'tenant_id' => $tenant->id,
                    'password' => Hash::make('password'),
                    'is_active' => true,
                ])
            );
            if ($clientRole) {
                $client->roles()->syncWithoutDetaching([$clientRole->id]);
            }
            $users->push($client);
        }

        return $users;
    }

    /**
     * Tạo projects với các status khác nhau
     */
    private function createProjects(Tenant $tenant, \Illuminate\Support\Collection $users): \Illuminate\Support\Collection
    {
        $this->command->info('   Creating projects...');

        $pmUsers = $users->filter(fn($u) => $u->email === 'pm1@zenamanage.test' || $u->email === 'pm2@zenamanage.test');
        $pm1 = $pmUsers->first();
        $pm2 = $pmUsers->skip(1)->first() ?? $pmUsers->first();

        $projects = [
            [
                'code' => 'PRJ-001',
                'name' => 'Website Redesign Project',
                'description' => 'Complete redesign of company website with modern UI/UX, responsive design, and improved performance.',
                'status' => 'active',
                'progress_pct' => 65,
                'completion_percentage' => 65,
                'budget_total' => 50000,
                'budget_planned' => 50000,
                'budget_actual' => 32500,
                'priority' => 'high',
                'risk_level' => 'medium',
                'start_date' => now()->subDays(45),
                'end_date' => now()->addDays(30),
                'owner_id' => $pm1->id ?? null,
                'last_activity_at' => now()->subHours(2),
            ],
            [
                'code' => 'PRJ-002',
                'name' => 'Mobile App Development',
                'description' => 'Development of iOS and Android mobile application with backend API integration.',
                'status' => 'active',
                'progress_pct' => 40,
                'completion_percentage' => 40,
                'budget_total' => 80000,
                'budget_planned' => 80000,
                'budget_actual' => 32000,
                'priority' => 'high',
                'risk_level' => 'low',
                'start_date' => now()->subDays(30),
                'end_date' => now()->addDays(60),
                'owner_id' => $pm2->id ?? null,
                'last_activity_at' => now()->subHours(5),
            ],
            [
                'code' => 'PRJ-003',
                'name' => 'Marketing Campaign Q1',
                'description' => 'Q1 marketing campaign for new product launch including social media, email marketing, and content creation.',
                'status' => 'active',
                'progress_pct' => 75,
                'completion_percentage' => 75,
                'budget_total' => 30000,
                'budget_planned' => 30000,
                'budget_actual' => 22500,
                'priority' => 'normal',
                'risk_level' => 'low',
                'start_date' => now()->subDays(20),
                'end_date' => now()->addDays(10),
                'owner_id' => $pm1->id ?? null,
                'last_activity_at' => now()->subHours(1),
            ],
            [
                'code' => 'PRJ-004',
                'name' => 'Database Migration',
                'description' => 'Migration from MySQL to PostgreSQL with zero downtime strategy.',
                'status' => 'on_hold',
                'progress_pct' => 30,
                'completion_percentage' => 30,
                'budget_total' => 25000,
                'budget_planned' => 25000,
                'budget_actual' => 7500,
                'priority' => 'normal',
                'risk_level' => 'high',
                'start_date' => now()->subDays(60),
                'end_date' => now()->addDays(30),
                'owner_id' => $pm2->id ?? null,
                'last_activity_at' => now()->subDays(5),
            ],
            [
                'code' => 'PRJ-005',
                'name' => 'E-commerce Platform',
                'description' => 'Build new e-commerce platform with payment integration, inventory management, and order tracking.',
                'status' => 'completed',
                'progress_pct' => 100,
                'completion_percentage' => 100,
                'budget_total' => 100000,
                'budget_planned' => 100000,
                'budget_actual' => 95000,
                'priority' => 'high',
                'risk_level' => 'medium',
                'start_date' => now()->subDays(120),
                'end_date' => now()->subDays(10),
                'owner_id' => $pm1->id ?? null,
                'last_activity_at' => now()->subDays(10),
            ],
            [
                'code' => 'PRJ-006',
                'name' => 'API Development',
                'description' => 'RESTful API development for mobile and web applications with documentation.',
                'status' => 'planning',
                'progress_pct' => 15,
                'completion_percentage' => 15,
                'budget_total' => 40000,
                'budget_planned' => 40000,
                'budget_actual' => 6000,
                'priority' => 'normal',
                'risk_level' => 'low',
                'start_date' => now()->subDays(10),
                'end_date' => now()->addDays(50),
                'owner_id' => $pm2->id ?? null,
                'last_activity_at' => now()->subDays(2),
            ],
            [
                'code' => 'PRJ-007',
                'name' => 'Security Audit',
                'description' => 'Comprehensive security audit and penetration testing for all systems.',
                'status' => 'active',
                'progress_pct' => 50,
                'completion_percentage' => 50,
                'budget_total' => 35000,
                'budget_planned' => 35000,
                'budget_actual' => 17500,
                'priority' => 'urgent',
                'risk_level' => 'high',
                'start_date' => now()->subDays(15),
                'end_date' => now()->addDays(15),
                'owner_id' => $pm1->id ?? null,
                'last_activity_at' => now()->subMinutes(30),
            ],
        ];

        $createdProjects = collect();
        foreach ($projects as $projectData) {
            $project = Project::updateOrCreate(
                ['code' => $projectData['code'], 'tenant_id' => $tenant->id],
                array_merge($projectData, ['tenant_id' => $tenant->id])
            );
            $createdProjects->push($project);
        }

        return $createdProjects;
    }

    /**
     * Tạo tasks với assignments
     */
    private function createTasksWithAssignments(Tenant $tenant, \Illuminate\Support\Collection $projects, \Illuminate\Support\Collection $users): void
    {
        $this->command->info('   Creating tasks with assignments...');

        $memberUsers = $users->filter(fn($u) => str_contains($u->email, 'member'));
        $pmUsers = $users->filter(fn($u) => str_contains($u->email, 'pm'));

        $taskTemplates = [
            'Website Redesign Project' => [
                ['name' => 'Gather requirements from stakeholders', 'status' => 'completed', 'priority' => 'high', 'progress_percent' => 100, 'estimated_hours' => 16, 'actual_hours' => 14],
                ['name' => 'Create wireframes and mockups', 'status' => 'completed', 'priority' => 'high', 'progress_percent' => 100, 'estimated_hours' => 40, 'actual_hours' => 42],
                ['name' => 'Design UI components', 'status' => 'in_progress', 'priority' => 'high', 'progress_percent' => 75, 'estimated_hours' => 60, 'actual_hours' => 45],
                ['name' => 'Implement frontend components', 'status' => 'in_progress', 'priority' => 'high', 'progress_percent' => 50, 'estimated_hours' => 120, 'actual_hours' => 60],
                ['name' => 'Backend API integration', 'status' => 'in_progress', 'priority' => 'normal', 'progress_percent' => 40, 'estimated_hours' => 80, 'actual_hours' => 32],
                ['name' => 'Testing and bug fixes', 'status' => 'backlog', 'priority' => 'normal', 'progress_percent' => 0, 'estimated_hours' => 60, 'actual_hours' => 0],
                ['name' => 'Deploy to production', 'status' => 'backlog', 'priority' => 'high', 'progress_percent' => 0, 'estimated_hours' => 8, 'actual_hours' => 0],
            ],
            'Mobile App Development' => [
                ['name' => 'Project setup and architecture', 'status' => 'completed', 'priority' => 'high', 'progress_percent' => 100, 'estimated_hours' => 16, 'actual_hours' => 16],
                ['name' => 'Design database schema', 'status' => 'completed', 'priority' => 'high', 'progress_percent' => 100, 'estimated_hours' => 24, 'actual_hours' => 22],
                ['name' => 'Develop backend API', 'status' => 'in_progress', 'priority' => 'high', 'progress_percent' => 60, 'estimated_hours' => 160, 'actual_hours' => 96],
                ['name' => 'iOS app development', 'status' => 'in_progress', 'priority' => 'high', 'progress_percent' => 30, 'estimated_hours' => 200, 'actual_hours' => 60],
                ['name' => 'Android app development', 'status' => 'in_progress', 'priority' => 'high', 'progress_percent' => 25, 'estimated_hours' => 180, 'actual_hours' => 45],
                ['name' => 'API integration testing', 'status' => 'backlog', 'priority' => 'normal', 'progress_percent' => 0, 'estimated_hours' => 40, 'actual_hours' => 0],
                ['name' => 'App store submission', 'status' => 'backlog', 'priority' => 'normal', 'progress_percent' => 0, 'estimated_hours' => 16, 'actual_hours' => 0],
            ],
            'Marketing Campaign Q1' => [
                ['name' => 'Market research and analysis', 'status' => 'completed', 'priority' => 'high', 'progress_percent' => 100, 'estimated_hours' => 40, 'actual_hours' => 38],
                ['name' => 'Create marketing content', 'status' => 'completed', 'priority' => 'high', 'progress_percent' => 100, 'estimated_hours' => 60, 'actual_hours' => 55],
                ['name' => 'Design marketing materials', 'status' => 'completed', 'priority' => 'normal', 'progress_percent' => 100, 'estimated_hours' => 40, 'actual_hours' => 42],
                ['name' => 'Launch social media campaign', 'status' => 'in_progress', 'priority' => 'high', 'progress_percent' => 80, 'estimated_hours' => 80, 'actual_hours' => 64],
                ['name' => 'Email marketing campaign', 'status' => 'in_progress', 'priority' => 'normal', 'progress_percent' => 60, 'estimated_hours' => 40, 'actual_hours' => 24],
                ['name' => 'Monitor and analyze results', 'status' => 'in_progress', 'priority' => 'normal', 'progress_percent' => 50, 'estimated_hours' => 60, 'actual_hours' => 30],
                ['name' => 'Generate ROI report', 'status' => 'backlog', 'priority' => 'normal', 'progress_percent' => 0, 'estimated_hours' => 16, 'actual_hours' => 0],
            ],
        ];

        foreach ($projects as $project) {
            $projectName = $project->name;
            if (!isset($taskTemplates[$projectName])) {
                // Generate generic tasks for projects without templates
                $genericTasks = [
                    ['name' => 'Project planning', 'status' => 'completed', 'priority' => 'high', 'progress_percent' => 100],
                    ['name' => 'Requirement gathering', 'status' => 'completed', 'priority' => 'high', 'progress_percent' => 100],
                    ['name' => 'Development', 'status' => 'in_progress', 'priority' => 'normal', 'progress_percent' => rand(30, 70)],
                    ['name' => 'Testing', 'status' => 'in_progress', 'priority' => 'normal', 'progress_percent' => rand(20, 60)],
                    ['name' => 'Deployment', 'status' => 'backlog', 'priority' => 'high', 'progress_percent' => 0],
                ];
                $taskTemplates[$projectName] = $genericTasks;
            }

            $tasks = $taskTemplates[$projectName] ?? [];
            $memberList = $memberUsers->values();
            $pmList = $pmUsers->values();

            foreach ($tasks as $index => $taskData) {
                $assignee = $memberList->count() > 0 ? $memberList[$index % $memberList->count()] : null;
                $pm = $pmList->count() > 0 ? $pmList[$index % $pmList->count()] : null;

                $task = Task::updateOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'project_id' => $project->id,
                        'name' => $taskData['name'],
                    ],
                    array_merge($taskData, [
                        'tenant_id' => $tenant->id,
                        'project_id' => $project->id,
                        'title' => $taskData['name'],
                        'description' => "Description for {$taskData['name']}",
                        'priority' => $taskData['priority'] ?? 'normal',
                        'status' => $taskData['status'] ?? 'backlog',
                        'progress_percent' => $taskData['progress_percent'] ?? 0,
                        'estimated_hours' => $taskData['estimated_hours'] ?? rand(8, 40),
                        'actual_hours' => $taskData['actual_hours'] ?? 0,
                        'assignee_id' => $assignee?->id,
                        'assigned_to' => $assignee?->id,
                        'created_by' => $pm?->id ?? $memberList->first()?->id,
                        'start_date' => now()->subDays(rand(1, 30)),
                        'end_date' => now()->addDays(rand(1, 30)),
                    ])
                );

                // Assign task to user
                if ($assignee) {
                    $assignmentId = (string) Str::ulid();
                    DB::table('task_assignments')->updateOrInsert(
                        [
                            'task_id' => $task->id,
                            'user_id' => $assignee->id,
                            'role' => 'assignee',
                        ],
                        [
                            'id' => $assignmentId,
                            'task_id' => $task->id,
                            'user_id' => $assignee->id,
                            'tenant_id' => $tenant->id,
                            'role' => 'assignee',
                            'assignment_type' => 'user',
                            'status' => $task->status === 'completed' ? 'completed' : ($task->status === 'in_progress' ? 'in_progress' : 'assigned'),
                            'assigned_hours' => $task->estimated_hours ?? 0,
                            'actual_hours' => $task->actual_hours ?? 0,
                            'assigned_at' => now()->subDays(rand(1, 30)),
                            'started_at' => $task->status === 'in_progress' || $task->status === 'completed' ? now()->subDays(rand(1, 20)) : null,
                            'completed_at' => $task->status === 'completed' ? now()->subDays(rand(1, 5)) : null,
                            'created_by' => $pm?->id ?? $memberList->first()?->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }
            }
        }
    }

    /**
     * Tạo task comments
     */
    private function createTaskComments(Tenant $tenant, \Illuminate\Support\Collection $users): void
    {
        $this->command->info('   Creating task comments...');

        $tasks = Task::where('tenant_id', $tenant->id)->get();
        $memberList = $users->filter(fn($u) => str_contains($u->email, 'member'))->values();
        $pmList = $users->filter(fn($u) => str_contains($u->email, 'pm'))->values();

        $commentTemplates = [
            'Great progress on this task! Keep it up.',
            'Can you provide more details on the implementation?',
            'I have reviewed the code and it looks good.',
            'This needs to be completed by end of week.',
            'Please update the documentation as well.',
            'The design looks excellent. Approved!',
            'Found a small bug. Fixing it now.',
            'All tests are passing. Ready for review.',
            'Need clarification on the requirements.',
            'This is blocking other tasks. Priority update needed.',
        ];

        foreach ($tasks->take(30) as $task) {
            // Create 2-5 comments per task
            $commentCount = rand(2, 5);
            $commentUsers = $users->random(min($commentCount, $users->count()));
            
            foreach ($commentUsers as $user) {
                TaskComment::create([
                    'tenant_id' => $tenant->id,
                    'task_id' => $task->id,
                    'user_id' => $user->id,
                    'content' => $commentTemplates[array_rand($commentTemplates)],
                    'type' => 'comment',
                    'is_internal' => rand(0, 1) === 1,
                    'is_pinned' => rand(0, 10) === 0, // 10% chance of being pinned
                    'created_at' => now()->subDays(rand(0, 7)),
                    'updated_at' => now()->subDays(rand(0, 7)),
                ]);
            }
        }
    }

    /**
     * Tạo notifications
     */
    private function createNotifications(Tenant $tenant, \Illuminate\Support\Collection $users, \Illuminate\Support\Collection $projects): void
    {
        $this->command->info('   Creating notifications...');

        $tasks = Task::where('tenant_id', $tenant->id)->get();

        $notificationTemplates = [
            [
                'type' => 'task_assigned',
                'title' => 'New Task Assigned',
                'body' => 'You have been assigned to a new task: {task_name}',
                'priority' => 'normal',
            ],
            [
                'type' => 'task_completed',
                'title' => 'Task Completed',
                'body' => 'Task "{task_name}" has been completed.',
                'priority' => 'normal',
            ],
            [
                'type' => 'task_overdue',
                'title' => 'Task Overdue',
                'body' => 'Task "{task_name}" is overdue.',
                'priority' => 'critical',
            ],
            [
                'type' => 'project_milestone',
                'title' => 'Project Milestone Reached',
                'body' => 'Project "{project_name}" has reached a milestone.',
                'priority' => 'normal',
            ],
            [
                'type' => 'comment_added',
                'title' => 'New Comment',
                'body' => 'A new comment was added to task "{task_name}".',
                'priority' => 'low',
            ],
        ];

        foreach ($users as $user) {
            // Create 5-10 notifications per user
            $notificationCount = rand(5, 10);
            
            for ($i = 0; $i < $notificationCount; $i++) {
                $template = $notificationTemplates[array_rand($notificationTemplates)];
                $task = $tasks->random();
                $project = $projects->random();

                $title = $template['title'];
                $body = str_replace(
                    ['{task_name}', '{project_name}'],
                    [$task->name, $project->name],
                    $template['body']
                );

                Notification::create([
                    'user_id' => $user->id,
                    'tenant_id' => $tenant->id,
                    'project_id' => $project->id,
                    'type' => $template['type'],
                    'priority' => $template['priority'],
                    'title' => $title,
                    'body' => $body,
                    'channel' => 'inapp',
                    'link_url' => '/app/projects/' . $project->id . '/tasks/' . $task->id,
                    'read_at' => rand(0, 1) === 1 ? now()->subDays(rand(1, 7)) : null, // 50% chance of being read
                    'created_at' => now()->subDays(rand(0, 14)),
                    'updated_at' => now()->subDays(rand(0, 14)),
                ]);
            }
        }
    }
}

